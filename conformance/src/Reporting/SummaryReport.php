<?php

declare(strict_types=1);

namespace Conformance\Reporting;

use Conformance\Discovery\TestCase;
use Conformance\TestGroup\TestGroup;
use Internal\Toml\Toml;
use RuntimeException;
use function htmlspecialchars;
use function preg_match;
use function preg_replace_callback;
use function sprintf;
use function trim;

final class SummaryReport
{
    private const DETAILS_DIR = 'tests';
    private const INDEX_FILE = 'results.html';
    private const STYLESHEET_FILE = 'report.css';
    private const STYLESHEET_SOURCE = __DIR__ . '/../../templates/report.css';

    /**
     * @param array<string, TestGroup> $testGroups
     * @param list<TestCase> $testCases
     * @param list<string> $tools
     */
    public function generate(
        string $resultsRoot,
        string $outputPath,
        array $testGroups,
        array $testCases,
        array $tools,
    ): void {
        $detailsDir = $resultsRoot . DIRECTORY_SEPARATOR . self::DETAILS_DIR;
        $this->prepareDetailsDir($detailsDir);
        $this->copyStylesheet($resultsRoot);

        // Detail pages first so the index can link to known files.
        foreach ($testCases as $testCase) {
            $group = $testGroups[$testCase->groupKey] ?? null;
            $detailHtml = $this->renderDetailPage($resultsRoot, $testCase, $group, $tools);
            $detailPath = $detailsDir . DIRECTORY_SEPARATOR . $testCase->name . '.html';

            if (file_put_contents($detailPath, $detailHtml) === false) {
                throw new RuntimeException(sprintf('Failed to write detail page: %s', $detailPath));
            }
        }

        $indexHtml = $this->renderIndexPage($resultsRoot, $testGroups, $testCases, $tools);

        if (file_put_contents($outputPath, $indexHtml) === false) {
            throw new RuntimeException(sprintf('Failed to write summary report: %s', $outputPath));
        }
    }

    /**
     * @param array<string, TestGroup> $testGroups
     * @param list<TestCase> $testCases
     * @param list<string> $tools
     */
    private function renderIndexPage(
        string $resultsRoot,
        array $testGroups,
        array $testCases,
        array $tools,
    ): string {
        $soundnessCases = array_values(array_filter(
            $testCases,
            fn (TestCase $testCase): bool => $this->testKind($testCase) !== 'style',
        ));
        $styleCases = array_values(array_filter(
            $testCases,
            fn (TestCase $testCase): bool => $this->testKind($testCase) === 'style',
        ));

        $body = [];
        $body[] = '<h1>PHP Typing Conformance Results</h1>';
        $body[] = '<p class="lead">Each row links to a per-test detail page with the source, expectations, and every analyzer&rsquo;s raw output.</p>';

        $body = array_merge($body, $this->renderLegend());

        $body[] = '<h2 class="section">Soundness &mdash; potential runtime type errors</h2>';
        $body[] = '<p class="section-note">Positives here flag code that can actually fail at runtime &mdash; type mismatches, null access, invalid arguments, uninitialized reads. <strong>Pass</strong> means the analyzer agrees with the expected diagnostic; rows whose test marks a declaration with <code>// T</code> instead report recognition and enforcement of the type spelling. See the legend above.</p>';
        $body = array_merge($body, $this->renderMatrix($resultsRoot, $testGroups, $soundnessCases, $tools, false));

        if ($styleCases !== []) {
            $body[] = '<h2 class="section">Style &amp; opinionated rules &mdash; no runtime-safety impact</h2>';
            $body[] = '<p class="section-note">Lint-style opinions and advisories (PHPStan strict-rules, deprecations, doc conventions) that do not change whether the code runs. Cells show whether each analyzer <em>opts into</em> reporting the rule &mdash; not a pass/fail verdict.</p>';
            $body = array_merge($body, $this->renderMatrix($resultsRoot, $testGroups, $styleCases, $tools, true));
        }

        $body = array_merge($body, $this->renderAnalyzerTable());
        $body = array_merge($body, $this->renderLanguageServerTable());

        return $this->renderPage('PHP Typing Conformance Results', $body, false);
    }

    /**
     * What each cell value means.
     *
     * Two vocabularies share the matrix. Most rows ask "does the analyzer catch
     * this unsafe code?" and answer Pass/Fail. The `// T`-marked rows ask "how
     * does the analyzer treat this type spelling?" and answer on two axes.
     * Without this legend the two read as one scale, which they are not.
     *
     * @return list<string>
     */
    private function renderLegend(): array
    {
        $verdicts = [
            ['pass', 'Pass', 'The analyzer&rsquo;s diagnostics match what the test expects.'],
            ['fail', 'Fail', 'They do not: an expected diagnostic is missing, or something unexpected is reported.'],
            ['by-design', 'Not reported (by design)', 'The analyzer declines to report the diagnostic and has said so upstream; the issue is linked in the cell notes. Hand-curated &mdash; the harness cannot tell this apart from a plain miss.'],
        ];

        $handling = [
            ['not-supported', 'Unrecognized', 'The spelling is not resolved at all: the analyzer reports an unresolvable type, an undeclared type, or a docblock parse error on the declaration. Expected whenever a test uses another analyzer&rsquo;s dialect &mdash; not a defect.'],
            ['pass', 'Enforced', 'The spelling is resolved <em>and</em> every value the type excludes is rejected.'],
            ['partial', 'Partly enforced (<em>n</em>/<em>m</em>)', 'Resolved, and some but not all of the excluded values are rejected. Common in files that probe several related spellings at once.'],
            ['falls-back', 'Widened to <em>X</em>', 'Resolved, but widened to <em>X</em>, so nothing is rejected. Not an error &mdash; just a coarser type. The base type is hand-curated.'],
            ['falls-back', 'Not enforced', 'Resolved, nothing rejected, and the base type it widened to has not been pinned down yet.'],
            ['by-design', 'Not enforced (by design)', 'Resolved and reportable, but the analyzer deliberately declines; the upstream issue is in the cell notes.'],
        ];

        $lines = [];
        $lines[] = '<details class="legend"><summary>Legend &mdash; what the cell values mean</summary>';

        $lines[] = '<p class="legend-intro"><strong>Verdict.</strong> Most rows ask whether the analyzer catches unsafe code.</p>';
        $lines[] = '<dl class="legend-list">';
        foreach ($verdicts as [$class, $label, $description]) {
            $lines[] = sprintf('<dt><span class="legend-swatch %s">%s</span></dt><dd>%s</dd>', $class, $label, $description);
        }
        $lines[] = '</dl>';

        $lines[] = '<p class="legend-intro"><strong>PHPDoc type handling.</strong> Tests that mark a declaration with <code>// T</code> ask a different question: not &ldquo;is the code unsafe?&rdquo; but &ldquo;how is this type spelling treated?&rdquo; That splits into two independent facets, and a single word cannot carry both:</p>';
        $lines[] = '<ul class="legend-facets">'
            . '<li><strong>Recognition</strong> &mdash; does the analyzer resolve the spelling? Read off the <code>// T</code> declaration lines. Level-independent.</li>'
            . '<li><strong>Enforcement</strong> &mdash; does it then reject the values the spelling excludes? Read off the <code>// E</code> call sites. For PHPStan this is gated by the level; recognition never is.</li>'
            . '</ul>';
        $lines[] = '<p class="legend-intro">The combinations:</p>';
        $lines[] = '<dl class="legend-list">';
        foreach ($handling as [$class, $label, $description]) {
            $lines[] = sprintf('<dt><span class="legend-swatch %s">%s</span></dt><dd>%s</dd>', $class, $label, $description);
        }
        $lines[] = '</dl>';

        $lines[] = '<p class="legend-intro"><strong>Tags.</strong></p>';
        $lines[] = '<dl class="legend-list">';
        $lines[] = '<dt><span class="legend-swatch plain"><small>reported Lv.5+</small></span></dt>'
            . '<dd>PHPStan only. The lowest level whose <em>rules</em> report the diagnostic this test expects. '
            . 'PHPStan levels switch rule sets on and off &mdash; type inference is identical at every level &mdash; so this is a '
            . 'configuration threshold, not a type-support tier. Level 5 turns on argument-type checks, level 6 missing typehints, '
            . 'level 8 nullables, level 9 explicit <code>mixed</code>. Each diagnostic on the detail page carries its own '
            . '<code>[reported-from-level=N]</code>; this tag shows the lowest one on an expected line.</dd>';
        $lines[] = '<dt><span class="legend-swatch plain"><small>&#9888; 3 false positives</small></span></dt>'
            . '<dd>Diagnostics on lines the test neither expects nor marks with <code>// T</code>. On a type-handling test these are the analyzer&rsquo;s own false positives &mdash; Phan resolving <code>number</code> as a class name and then rejecting <code>1</code> for it, say. Hover for the line numbers.</dd>';
        $lines[] = '<dt><span class="legend-swatch plain"><small>(strict)</small></span></dt>'
            . '<dd>PHPStan only. Nothing is reported with the standard config; only <code>phpstan-strict-rules</code> catches it.</dd>';
        $lines[] = '<dt><span class="legend-swatch plain"><small>(pzoom&ne;)</small></span></dt>'
            . '<dd>Psalm only. pzoom, the Rust port of Psalm, flags a different set of lines than Psalm itself.</dd>';
        $lines[] = '</dl>';

        $lines[] = '</details>';

        return $lines;
    }

    /**
     * A reference table of the analyzers compared above.
     *
     * @return list<string>
     */
    private function renderAnalyzerTable(): array
    {
        // Ordered by initial release (oldest first).
        //
        // Every tool here is a static analyzer, so "static analyzer" is not a
        // classification — it is the entry criterion. What actually
        // distinguishes them are three independent axes, and the old single
        // "Kind" column picked a different one for each row (Intelephense by
        // interface, NoVerify by analysis, Mago by packaging), which made the
        // values look mutually exclusive when they are not.
        //
        // `expansion` renders the name as <abbr title="...">. Not a strict
        // "the name is short for this" claim for every row — Psalm's is
        // phrased as "also referred to as", since its creator's own framing
        // ("or, if you prefer, the PHP Static Analysis Linting Machine",
        // Vimeo Engineering Blog, 2018) makes it an optional backronym, not
        // the name's origin, unlike PHPStan's README and composer.json, which
        // read "PHPStan - PHP Static Analysis Tool" outright.
        //
        // The old single "Current maintainer" column mixed two different
        // questions and picked whichever answer happened to exist: an org
        // name for PHPStan/NoVerify/Mago/PHP;STEINS, but a person's name for
        // Phan and Psalm — where maintenance actually passed from the founder
        // to someone else. Split into two columns that each answer one
        // question for every row:
        //
        // - Organization: the entity currently behind the project, if any,
        //   linked to that org's own page. Every row states this positively —
        //   no bare "—", since a dash reads as "not checked" once other rows
        //   carry real values, which would undo the point of separating this
        //   from Lead maintainer in the first place. Three kinds of answer:
        //   - A company or the founder's own formalized entity (PHPStan
        //     s.r.o., Carthage Software, TypedDuck/rigortype, VK.COM/VKCOM,
        //     and Intelephense itself — its footer carries an Australian
        //     Business Number, so "Intelephense" is a registered business,
        //     not just a product name). Verified via each org's own page, not
        //     assumed from the name; the display text is that page's own
        //     name, not the URL slug (VKCOM's is "VK.COM", rigortype's is
        //     "TypedDuck").
        //   - "Community (org)" for Phan and Psalm, which have no company at
        //     all, just a multi-contributor GitHub home with no single
        //     commercial owner: github.com/phan for Phan, github.com/psalm
        //     (a separate community-packages org, not vimeo/psalm itself) for
        //     Psalm.
        //   - "Personal" for mir and Pzoom, genuinely one person's project
        //     with no separate entity, formal or informal — the same
        //     information Lead maintainer already carries, restated here so
        //     the column never falls back to an ambiguous dash.
        // - Lead maintainer: the individual currently driving day-to-day
        //   development. Usually the founder, in which case it stays plain
        //   text with no link -- Founder already links that person, and a
        //   second link to the same profile would just be noise. `leadUrl`
        //   and `leadNote` are set only for the rows where the lead
        //   verifiably differs from the founder, and render as a link with a
        //   hover note rather than silently repeating the founder's name.
        //   Phan's is Rasmus Lerdorf, not Tyson Andre: every one of the last
        //   10 GitHub releases (5.5.2 through 6.0.7) was cut by rlerdorf, and
        //   Andre has no recent commits or releases despite being widely
        //   cited as "the" current maintainer. NoVerify is left blank rather
        //   than guessed: recent releases (v0.5.4, v0.5.5) were cut by a
        //   different person than the founder, but that alone doesn't
        //   establish who currently drives the project, unlike Phan's
        //   unambiguous single-author release history.
        //
        // Founder links each named individual to their own GitHub profile,
        // verified rather than guessed (rlerdorf, morria, muglug,
        // ondrejmirtes, bmewburn, azjezz, jorgsowa, zonuexe, and
        // YuriyNasretdinov all checked directly). Parentheticals that just
        // name the linked GitHub handle/org and add nothing the link itself
        // doesn't already show -- "(Carthage Software)", "(rigortype)",
        // "(muglug)" -- are dropped here; "(Etsy)" and "(Vimeo)" stay because
        // they name an employer, not a handle.
        //
        // phpy's row names the tool "phpy / PHPTools" outright, in the name
        // field itself like every other row -- no <abbr> tooltip, since
        // unlike every other row the thing being compared here is not the
        // whole product: phpy (github.com/DEVSENSE/phpy) is a thin CLI
        // wrapper DEVSENSE published 2025-05 (v0.1.0) around the same
        // closed-source analysis engine that has shipped inside PHP Tools
        // for Visual Studio since 2012 -- DEVSENSE's own blog post
        // introduces it as "a proof of concept" for a standalone language
        // server, not a ground-up product. Initial release is phpy's own
        // 2025 start, not PHP Tools' 2012 origin: every other row tracks the
        // specific artifact being compared, not whatever earlier tooling its
        // maintainer previously built (NoVerify's row doesn't inherit VK's
        // earlier internal tooling either). License is "Proprietary
        // (freemium)" like Intelephense, verified against DEVSENSE's own
        // Community License page: free for OSI-licensed open-source
        // projects, education, and businesses under 250 seats/$1M revenue;
        // above that it requires a paid license. Organization links
        // DEVSENSE's own homepage, the same standard applied to
        // Intelephense's own site. Founder and Lead maintainer are both
        // Jakub Míšek -- DEVSENSE's founder and the sole author on phpy's
        // GitHub releases.
        //
        // [name, expansion, homepage, analysis, interface, bundled, language,
        //  founder(raw HTML), backing, backingUrl, lead, leadUrl, leadNote,
        //  license, initial, latest, ast, announceUrl, announceLabel]
        $rows = [
            ['Phan', '', 'https://github.com/phan/phan', 'Type checker', 'CLI, LSP', 'Fixer (narrow)', 'PHP', '<a href="https://github.com/rlerdorf" target="_blank" rel="noopener">Rasmus Lerdorf</a> &amp; <a href="https://github.com/morria" target="_blank" rel="noopener">Andrew Morrison</a> (Etsy)', 'Community (phan)', 'https://github.com/phan', 'Rasmus Lerdorf', 'https://github.com/rlerdorf', 'Every one of the last 10 GitHub releases (5.5.2 through 6.0.7) was cut by rlerdorf; Tyson Andre has no recent commits or releases despite being widely cited elsewhere as the current maintainer.', 'MIT', '2015', '6.0.7 (2026-06-22)', 'ext-ast / tolerant-php-parser', 'https://talks.php.net/ph16', 'Deploying PHP 7 (talk)'],
            ['Psalm', 'Also referred to as a “PHP Static Analysis Linting Machine”', 'https://psalm.dev', 'Type checker', 'CLI, LSP', 'Fixer, refactorer', 'PHP', '<a href="https://github.com/muglug" target="_blank" rel="noopener">Matt Brown</a> (Vimeo)', 'Community (psalm)', 'https://github.com/psalm', 'Daniil Gentili', 'https://github.com/danog', 'Sole active maintainer since Vimeo stepped back; the repository still lives under the vimeo/psalm GitHub org.', 'MIT', '2016', '6.16.1 (2026-03-19)', 'nikic/PHP-Parser', 'https://medium.com/vimeo-engineering-blog/automated-type-inference-for-dynamically-typed-programs-6e79197e5420', 'Automated type inference'],
            ['PHPStan', 'PHP Static Analysis Tool', 'https://phpstan.org', 'Type checker', 'CLI', '—', 'PHP', '<a href="https://github.com/ondrejmirtes" target="_blank" rel="noopener">Ondřej Mirtes</a>', 'PHPStan s.r.o.', 'https://github.com/phpstan', 'Ondřej Mirtes', '', '', 'MIT', '2016', '2.2.5 (2026-07-05)', 'nikic/PHP-Parser', 'https://phpstan.org/blog/find-bugs-in-your-code-without-writing-tests', 'Find Bugs Without Tests'],
            ['Intelephense', '', 'https://intelephense.com', 'Code intelligence', 'LSP', 'Formatter, rename', 'TypeScript', '<a href="https://github.com/bmewburn" target="_blank" rel="noopener">Ben Mewburn</a>', 'Intelephense', 'https://intelephense.com', 'Ben Mewburn', '', '', 'Proprietary (freemium)', '2017', '1.18.5 (2026-06-21)', 'own parser', '', ''],
            ['NoVerify', '', 'https://github.com/VKCOM/noverify', 'Type-aware linter', 'CLI', '—', 'Go', '<a href="https://github.com/YuriyNasretdinov" target="_blank" rel="noopener">Yuriy Nasretdinov</a> (VK)', 'VK.COM', 'https://github.com/VKCOM', '—', '', '', 'MIT', '2019', '0.5.5 (2025-04-22)', 'VKCOM/php-parser', 'https://habr.com/ru/companies/vk/articles/442284/', 'VK open-sources it (Habr)'],
            ['Mago', '', 'https://mago.carthage.software', 'Type checker', 'CLI', 'Linter, formatter, arch guard', 'Rust', '<a href="https://github.com/azjezz" target="_blank" rel="noopener">Saif Eddin Gmati</a>', 'Carthage Software', 'https://github.com/carthage-software', 'Saif Eddin Gmati', '', '', 'MIT OR Apache-2.0', '2024', '1.44.0 (2026-07-18)', 'own parser', 'https://github.com/carthage-software/mago/releases/tag/1.0.0', 'Mago 1.0.0'],
            ['phpy / PHPTools', '', 'https://github.com/DEVSENSE/phpy', 'Code intelligence', 'CLI, LSP', 'Formatter', 'C#, TypeScript', '<a href="https://github.com/jakubmisek" target="_blank" rel="noopener">Jakub Míšek</a>', 'DEVSENSE', 'https://www.devsense.com', 'Jakub Míšek', '', '', 'Proprietary (freemium)', '2025', '1.0.18519 (2026-02-19)', 'own (C#/.NET compiled to WASM)', 'https://blog.devsense.com/2025/update-1-58-benchmarks/', 'phpy: a proof-of-concept CLI'],
            ['mir', '', 'https://github.com/jorgsowa/mir', 'Type checker', 'CLI', '—', 'Rust', '<a href="https://github.com/jorgsowa" target="_blank" rel="noopener">Jorg Sowa</a>', 'Personal', '', 'Jorg Sowa', '', '', 'MIT', '2026', '0.60.0 (2026-07-18)', 'own (php-rs-parser)', '', ''],
            ['Pzoom', '', 'https://pzoom.dev', 'Type checker', 'CLI', '—', 'Rust', '<a href="https://github.com/muglug" target="_blank" rel="noopener">Matt Brown</a>', 'Personal', '', 'Matt Brown', '', '', 'MIT', '2026', 'unversioned (2026-06-24)', 'Mago parser', 'https://mattbrown.dev/articles/from-psalm-to-pzoom', 'From Psalm to Pzoom'],
            ['PHP;STEINS', '', 'https://github.com/rigortype/steins', 'Type checker', 'CLI', 'Annotator, transforms', 'Rust', '<a href="https://github.com/zonuexe" target="_blank" rel="noopener">USAMI Kenta</a>', 'TypedDuck', 'https://github.com/rigortype', 'USAMI Kenta', '', '', 'Apache-2.0', '2026', '0.1.0 (2026-07-24)', 'Mago parser (fork)', '', ''],
        ];

        $headers = ['Analyzer', 'Analysis', 'Interface', 'Bundled with it', 'Language', 'Founder', 'Organization', 'Lead maintainer', 'License', 'Initial release', 'Latest release', 'AST / parser', 'Release announcement'];

        $lines = [];
        $lines[] = '<h2 class="section">Analyzers</h2>';
        $lines[] = '<p class="section-note">Reference metadata for each analyzer compared above; versions are those pinned by this suite. <strong>Analysis</strong> is the one column whose values are coined here rather than read off the project: a <em>type checker</em> aims at type correctness, a <em>type-aware linter</em> at a rule catalogue that inference sharpens, and <em>code intelligence</em> infers types mainly to drive completion and navigation, with diagnostics one feature among many.</p>';
        $lines[] = '<div class="table-scroll"><table class="analyzer-meta">';
        $lines[] = '<thead><tr>';
        foreach ($headers as $header) {
            $lines[] = '<th scope="col">' . htmlspecialchars($header) . '</th>';
        }
        $lines[] = '</tr></thead><tbody>';

        foreach ($rows as [$name, $expansion, $url, $analysis, $interface, $bundled, $language, $founder, $backing, $backingUrl, $lead, $leadUrl, $leadNote, $license, $initial, $latest, $ast, $announceUrl, $announceLabel]) {
            $announcement = $announceUrl !== ''
                ? sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', htmlspecialchars($announceUrl), htmlspecialchars($announceLabel))
                : '<span class="none">—</span>';

            $label = $expansion === ''
                ? htmlspecialchars($name)
                : sprintf('<abbr title="%s">%s</abbr>', htmlspecialchars($expansion), htmlspecialchars($name));

            $backingCell = $backingUrl === ''
                ? htmlspecialchars($backing)
                : sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', htmlspecialchars($backingUrl), htmlspecialchars($backing));

            // Linked only when the lead differs from the founder: the
            // founder's own link already covers the "same person" case, so a
            // second link there would just point back at itself.
            $leadCell = htmlspecialchars($lead);
            if ($leadUrl !== '') {
                $leadCell = sprintf(
                    '<a href="%s" target="_blank" rel="noopener"%s>%s</a>',
                    htmlspecialchars($leadUrl),
                    $leadNote === '' ? '' : sprintf(' title="%s"', htmlspecialchars($leadNote)),
                    htmlspecialchars($lead),
                );
            } elseif ($leadNote !== '') {
                $leadCell = sprintf(
                    '<span class="succession" title="%s">%s</span>',
                    htmlspecialchars($leadNote),
                    htmlspecialchars($lead),
                );
            }

            $lines[] = '<tr>'
                . sprintf('<th class="tool-name"><a href="%s" target="_blank" rel="noopener">%s</a></th>', htmlspecialchars($url), $label)
                . '<td>' . htmlspecialchars($analysis) . '</td>'
                . '<td>' . htmlspecialchars($interface) . '</td>'
                . '<td>' . htmlspecialchars($bundled) . '</td>'
                . '<td>' . htmlspecialchars($language) . '</td>'
                . '<td>' . $founder . '</td>'
                . '<td>' . $backingCell . '</td>'
                . '<td>' . $leadCell . '</td>'
                . '<td>' . htmlspecialchars($license) . '</td>'
                . '<td>' . $this->timeYear($initial) . '</td>'
                . '<td>' . $this->timeLatestRelease($latest) . '</td>'
                . '<td>' . htmlspecialchars($ast) . '</td>'
                . '<td>' . $announcement . '</td>'
                . '</tr>';
        }

        $lines[] = '</tbody></table></div>';

        return $lines;
    }

    /**
     * A reference table of PHP language servers.
     *
     * Deliberately a separate table from the analyzers above, not extra rows
     * in it. The analyzer table's entry criterion is "this tool is a static
     * analyzer we run against the conformance suite"; four of these six have
     * not been run at all, and two of them are not analyzers in the first
     * place -- they only drive somebody else's. Merging them would put unrun
     * tools in a table whose whole point is the matrix above it.
     *
     * Intelephense and Psalm appear in both tables, as the same artifact in
     * each -- unlike the php-lsp/mir and devsense-php-ls/phpy pairs below,
     * which are two artifacts apiece. That is not duplication to be cleaned
     * up: both genuinely satisfy both entry criteria, and dropping either
     * from either table would misrepresent it. The analyzer table records how
     * they score; this one records what they are. Psalm makes the case
     * plainly -- the LSP server is the same ProjectAnalyzer the CLI runs, so
     * its matrix row and its row here describe one engine reached two ways.
     *
     * Phan advertises "CLI, LSP" in the analyzer table too, so it qualifies
     * here on the same grounds and is simply not researched yet.
     *
     * Deferred: which LSP capabilities each server actually advertises is not
     * a column yet. The material for it exists -- Psalm's server declares
     * documentSymbolProvider, workspaceSymbolProvider, referencesProvider and
     * documentHighlightProvider false outright and has no rename or
     * formatting provider, while Intelephense gates rename and inlay hints
     * behind a paid key -- but capability names are a fixed protocol
     * vocabulary and deserve their own compatibility matrix rather than more
     * prose in "Bundled with it", which is where that detail currently sits
     * as hover notes.
     *
     * IMPORTANT: every cell here records what the project itself claims, from
     * its README, docs, changelog, release notes, package metadata, or its
     * maintainer's own blog. Except for Intelephense's and Psalm's results in
     * the matrix above, nothing in this section has been verified by running
     * the tool -- including those two projects' own claims here, which are
     * recorded on the same terms as everyone else's. Where a project does not state
     * something, the cell says "Not stated" rather than carrying a guess --
     * see the DEVSENSE row, whose implementation language and parser are
     * inferable from the vendor's history but nowhere actually claimed for
     * this artifact.
     *
     * The axis that separates these rows is where diagnostics come from,
     * which is exactly the axis the analyzer table cannot express:
     *
     * - "Adapter" -- the server shells out to third-party analyzers and
     *   republishes their diagnostics over LSP.
     * - "Own engine" -- the server infers and diagnoses by itself.
     * - "Own engine + adapter" -- both, which is what Phpactor and PHPantom
     *   each claim. Neither is honestly one or the other: Phpactor ships its
     *   own Worse Reflection inference plus eleven built-in diagnostic
     *   providers *and* opt-in extension packages that run PHPStan or Psalm;
     *   PHPantom ships its own type engine *and* auto-detects vendor/bin
     *   PHPStan, PHPCS and Mago to fold their output in. Collapsing either to
     *   a single value would misreport the project's own description.
     *
     * php-lsp is "Own engine" even though the engine (mir) is a separate
     * repository and crate family: its own architecture doc frames mir as its
     * static-analysis engine rather than as a third-party tool it adapts, the
     * two share an author, and the CHANGELOG records mir being extracted from
     * a sibling path dependency rather than adopted from outside. mir already
     * has a row in the analyzer table above; the "Analyzers driven" cell
     * marks that with "(in-house)" so the relationship is visible without
     * implying php-lsp integrates a foreign analyzer.
     *
     * devsense-php-ls is the counterpart case: phpy, which the analyzer table
     * above does have a row for, is the CLI frontend of *this* package rather
     * than a separate product -- phpy's package.json depends on
     * devsense-php-ls outright, and DEVSENSE's own announcement calls phpy "a
     * proof of concept for our new standalone language server". The two are
     * versioned independently and have drifted (the server is on 1.0.19075,
     * phpy still on 1.0.18519), which is why each gets its own row and its
     * own release date rather than one shared entry.
     *
     * Founder / Organization / Lead maintainer follow the analyzer table's
     * rules exactly, including the "Community (org)" vs "Personal" split.
     * PHPantom-dev and phpactor are both GitHub orgs with no company behind
     * them, so both read "Community (org)"; jorgsowa is a user account, so
     * php-lsp reads "Personal". Lead stays plain text wherever it is the
     * founder, since Founder already links that person.
     *
     * One sourcing caveat worth keeping: Dan Leech's GitHub profile sets its
     * name field to the handle "dantleech" rather than to a name, so the
     * "Dan Leech" spelling comes from the personal blog that profile links
     * to, not from the profile itself. Phpactor's announcement cell points at
     * that blog's "Three Years of Phpactor" -- a retrospective rather than a
     * launch post, which is the closest first-party equivalent the project
     * has, the same latitude Phan's row already takes with a conference talk.
     *
     * @return list<string>
     */
    private function renderLanguageServerTable(): array
    {
        // Ordered by initial release (oldest first), like the analyzers.
        //
        // `note` keys attach a hover explanation to the preceding cell and
        // are used only where the short value would otherwise overstate the
        // claim -- "Own engine + adapter" needs to say which half is which,
        // "Not stated" needs to say what *is* known instead.
        $rows = [
            [
                'name' => 'Intelephense',
                'url' => 'https://intelephense.com',
                'diagnostics' => 'Own engine',
                'diagnosticsNote' => 'Closed source. Its own wording is “multiple diagnostics for open files via an error tolerant parser and powerful static analysis engine”. Its settings contract reaches past undefined-symbol checks into real type diagnostics: intelephense.diagnostics.typeErrors covers “type compatibility for assignments and returns”, alongside argumentCount, implementationErrors, memberAccess and strictTypes.',
                'analyzers' => '—',
                'analyzersNote' => 'None claimed: no Intelephense source mentions running PHPStan, Psalm, Phan or PHP_CodeSniffer. It instead claims to read their PHPDoc dialects itself — “advanced PHPDoc type system supporting templates and callable signatures”, @psalm-assert, class-string<T>, conditional and DNF types — plus PHPStorm metadata files.',
                'bundled' => 'Formatter, rename, code actions, inlay hints',
                'language' => 'TypeScript',
                'founder' => '<a href="https://github.com/bmewburn" target="_blank" rel="noopener">Ben Mewburn</a>',
                'org' => 'Intelephense',
                'orgUrl' => 'https://intelephense.com',
                'lead' => 'Ben Mewburn',
                'leadUrl' => '',
                'leadNote' => '',
                'license' => 'Proprietary (freemium)',
                'licenseNote' => 'Its own words: “Intelephense is released to end users under a freemium model.” Free covers completion, signature help, go-to-definition, find references, symbol search, hover, PSR-12 formatting and the diagnostics themselves. A one-off key (US$35 personal, US$75/user business) unlocks rename, code actions, code lens, inlay hints, type hierarchy, find implementations, go-to-type-definition, code folding and @mixin support. So the diagnostics this table is about are entirely in the free tier.',
                'initial' => '2017',
                'latest' => '1.18.5 (2026-06-21)',
                'ast' => 'own parser',
                'astNote' => 'Never described as “own parser” in so many words — the developer’s own phrase is “error tolerant parser”. Read as in-house because early versions depended on php7parser, which Ben Mewburn also wrote; the current server bundles its parser closed-source, so the dependency is no longer visible.',
                'announceUrl' => '',
                'announceLabel' => '',
            ],
            [
                'name' => 'Phpactor',
                'url' => 'https://phpactor.readthedocs.io/en/master/',
                'diagnostics' => 'Own engine + adapter',
                'diagnosticsNote' => 'Ships its own Worse Reflection inference and eleven built-in diagnostic providers; the LSP support matrix additionally advertises “support for integrating with phpstan, Psalm and php-cs-fixer”.',
                'analyzers' => 'PHPStan, Psalm',
                'analyzersNote' => 'Opt-in extension packages, not core: phpactor/language-server-phpstan-extension and phpactor/language-server-psalm-extension. php-cs-fixer and PHP_CodeSniffer integrate the same way, for formatting rather than type diagnostics.',
                'bundled' => 'Refactorings, code generation, VIM plugin',
                'language' => 'PHP',
                'founder' => '<a href="https://github.com/dantleech" target="_blank" rel="noopener">Dan Leech</a>',
                'org' => 'Community (phpactor)',
                'orgUrl' => 'https://github.com/phpactor',
                'lead' => 'Dan Leech',
                'leadUrl' => '',
                'leadNote' => '',
                'license' => 'MIT',
                'licenseNote' => '',
                'initial' => '2018',
                'latest' => '2026.07.22.0 (2026-07-22)',
                'ast' => 'tolerant-php-parser (fork)',
                'astNote' => 'A fork of Microsoft’s tolerant-php-parser maintained under the Phpactor org; the founder’s blog says nikic/PHP-Parser was tried first and dropped because the tolerant parser “was designed exactly for Phpactor’s use case”.',
                'announceUrl' => 'https://www.dantleech.com/blog/2018/08/19/three-years-of-phpactor/',
                'announceLabel' => 'Three Years of Phpactor',
            ],
            [
                'name' => 'Psalm',
                'url' => 'https://psalm.dev/docs/running_psalm/language_server/',
                'diagnostics' => 'Own engine',
                'diagnosticsNote' => 'Literally the same engine as the CLI: the server builds a ProjectAnalyzer and Codebase, calls analyzeFiles(), and maps Psalm’s own IssueData onto LSP Diagnostic objects. The v3 announcement describes what the editor shows as Psalm’s “regular error reports”.',
                'analyzers' => '—',
                'analyzersNote' => 'None claimed. Neither the docs nor the server source mentions running PHPStan, PHP_CodeSniffer or php-cs-fixer.',
                'bundled' => 'Psalm CLI, Psalter fixer — one package',
                'bundledNote' => 'The server is the psalm-language-server bin inside vimeo/psalm, never a separate package. Its own docs scope it tightly: “diagnostics …, go-to-definition and hover, with limited support for autocompletion (PRs are welcome!)”, and the server declares documentSymbolProvider, workspaceSymbolProvider, referencesProvider and documentHighlightProvider as false outright, with no rename or formatting provider at all. Completion is opt-in; code actions require the client to advertise publishDiagnostics.dataSupport.',
                'language' => 'PHP',
                'founder' => '<a href="https://github.com/muglug" target="_blank" rel="noopener">Matt Brown</a> (Vimeo)',
                'org' => 'Community (psalm)',
                'orgUrl' => 'https://github.com/psalm',
                'lead' => 'Daniil Gentili',
                'leadUrl' => 'https://github.com/danog',
                'leadNote' => 'The README names him “the only active maintainer of Psalm”, and he is also the most active author on the LanguageServer directory itself. The VS Code client is the exception: it lives in its own repo, psalm/psalm-vscode-plugin, where Andrew Nagy does most of the committing and merging.',
                'license' => 'MIT',
                'licenseNote' => '',
                'initial' => '2018',
                'initialNote' => 'The server binary landed quietly in 2.0.15 (2018-10-19), a commit titled “Add server mode support with error reporting only”; the psalm-language-server bin was in composer.json by that tag. It only got a public announcement two months later, with Psalm 3.',
                'latest' => '6.16.1 (2026-03-19)',
                'ast' => 'nikic/PHP-Parser',
                'astNote' => 'Same parser as the CLI. The LSP wire types are not in-tree either — they come from felixfbecker/language-server-protocol and danog/advanced-json-rpc.',
                'announceUrl' => 'https://psalm.dev/articles/announcing-psalm-v3',
                'announceLabel' => 'Announcing Psalm v3',
            ],
            [
                'name' => 'devsense-php-ls',
                'url' => 'https://www.npmjs.com/package/devsense-php-ls',
                'diagnostics' => 'Own engine',
                'diagnosticsNote' => 'Closed source, shipped as per-OS/CPU native binaries under node_modules. The same engine that phpy wraps and that PHP Tools for Visual Studio and VS Code embed.',
                'analyzers' => '—',
                'analyzersNote' => 'DEVSENSE claims “support for PHPStan, Psalm, PHPDoc Generics, Laravel Idea, and other annotations” — reading those annotation dialects, not running those tools. No DEVSENSE source describes invoking a third-party analyzer.',
                'bundled' => 'Formatter, phpy CLI frontend',
                'language' => 'Not stated',
                'languageNote' => 'DEVSENSE does not state the engine’s implementation language for this package. It ships as native per-OS binaries (devsense-php-ls-darwin-arm64 and siblings), and the company’s Phalanger heritage is C#/.NET, but neither is claimed for this artifact.',
                'founder' => '<a href="https://github.com/jakubmisek" target="_blank" rel="noopener">Jakub Míšek</a>',
                'org' => 'DEVSENSE',
                'orgUrl' => 'https://www.devsense.com',
                'lead' => 'Jakub Míšek',
                'leadUrl' => '',
                'leadNote' => '',
                'license' => 'Proprietary (freemium)',
                'licenseNote' => 'package.json declares ISC, which covers the npm wrapper only: the README states the functionality itself is “provided to end-users under a freemium model”, activated with a license key. DEVSENSE’s Community License is free for OSI-licensed open source, education, and any entity under 250 seats and US$1M revenue.',
                'initial' => '2025',
                'latest' => '1.0.19075 (2026-07-16)',
                'ast' => 'Not stated',
                'astNote' => 'No DEVSENSE page names the parser or AST behind the engine.',
                'announceUrl' => 'https://blog.devsense.com/2025/update-1-58-benchmarks/',
                'announceLabel' => 'A standalone language server',
            ],
            [
                'name' => 'PHPantom',
                'url' => 'https://phpantom-dev.github.io/phpantom_lsp/',
                'diagnostics' => 'Own engine + adapter',
                'diagnosticsNote' => 'Its own type engine produces the diagnostics; the docs additionally advertise “PHPStan, PHPCS, and Mago integration. Run external tools on save and surface their diagnostics in the editor.”',
                'analyzers' => 'PHPStan, PHPCS, Mago',
                'analyzersNote' => 'Bundled in the single binary rather than plugins, invoked as subprocesses and auto-detected from vendor/bin then $PATH; Mago only when a mago.toml exists at the workspace root. Note Mago plays two roles here — optional external analyzer, and the parser the server itself is built on.',
                'bundled' => 'Formatter, refactorings, CLI (analyze, fix)',
                'language' => 'Rust',
                'founder' => '<a href="https://github.com/AJenbo" target="_blank" rel="noopener">Anders Jenbo</a>',
                'org' => 'Community (PHPantom-dev)',
                'orgUrl' => 'https://github.com/PHPantom-dev',
                'lead' => 'Anders Jenbo',
                'leadUrl' => '',
                'leadNote' => '',
                'license' => 'MIT',
                'licenseNote' => '',
                'initial' => '2026',
                'latest' => '0.9.0 (2026-07-19)',
                'ast' => 'Mago parser (mago-syntax)',
                'astNote' => 'The README credits “Mago: the PHP parser that powers all of PHPantom’s AST analysis”; Cargo.toml exact-pins mago-syntax and a half-dozen sibling mago-* crates.',
                'announceUrl' => '',
                'announceLabel' => '',
            ],
            [
                'name' => 'php-lsp',
                'url' => 'https://github.com/jorgsowa/php-lsp',
                'diagnostics' => 'Own engine',
                'diagnosticsNote' => 'Its architecture doc names the mir-php crates — mir-analyzer, mir-codebase, mir-issues, mir-types — as its static analysis, and the CHANGELOG records the older in-tree TypeMap inference being deleted once “mir is now the sole source of truth for all type inference”.',
                'analyzers' => 'mir (in-house)',
                'analyzersNote' => 'mir has its own row in the analyzer table above. It is a separate repository and crate family, but by the same author, and the CHANGELOG shows it was extracted from a sibling path dependency rather than adopted from outside — so it is an in-house engine, not a third-party analyzer being adapted.',
                'bundled' => 'Refactorings, code actions; formatting via php-cs-fixer or phpcbf',
                'language' => 'Rust',
                'founder' => '<a href="https://github.com/jorgsowa" target="_blank" rel="noopener">Jorg Sowa</a>',
                'org' => 'Personal',
                'orgUrl' => '',
                'lead' => 'Jorg Sowa',
                'leadUrl' => '',
                'leadNote' => '',
                'license' => 'MIT',
                'licenseNote' => '',
                'initial' => '2026',
                'latest' => '0.20.0 (2026-07-19)',
                'ast' => 'own (php-rs-parser)',
                'astNote' => 'Dedicated php-rs-parser and php-ast crates, published separately by the same author.',
                'announceUrl' => '',
                'announceLabel' => '',
            ],
        ];

        $headers = ['Language server', 'Diagnostics', 'Analyzers driven', 'Bundled with it', 'Language', 'Founder', 'Organization', 'Lead maintainer', 'License', 'Initial release', 'Latest release', 'AST / parser', 'Release announcement'];

        $lines = [];
        $lines[] = '<h2 class="section">Language servers</h2>';
        $lines[] = '<p class="section-note"><strong>Only Intelephense and Psalm have been run against the conformance suite; the other four have not.</strong> Their scores live in the matrix above, not here. Every cell in this table records what the project claims about itself &mdash; from its README, docs, changelog, release notes, package metadata, or its maintainer&rsquo;s own blog &mdash; and no claim here has been verified by executing the tool, those two included. Cells read <em>Not stated</em> where the project simply does not say.</p>';
        $lines[] = '<p class="section-note">Four rows also appear in the analyzer table above. <strong>Intelephense</strong> and <strong>Psalm</strong> are in both as the same tool &mdash; Psalm&rsquo;s server runs the analyzer its CLI does, reached over the protocol instead of the command line. The other two are pairs: <strong>php-lsp</strong> drives <em>mir</em>, and <em>phpy</em> is the CLI frontend of <strong>devsense-php-ls</strong>. Each pair is versioned separately, so each artifact keeps its own row and its own dates. <a href="https://github.com/phan/phan" target="_blank" rel="noopener">Phan</a> ships a language server too and belongs here; it has simply not been researched yet.</p>';
        $lines[] = '<div class="table-scroll"><table class="analyzer-meta">';
        $lines[] = '<thead><tr>';
        foreach ($headers as $header) {
            $lines[] = '<th scope="col">' . htmlspecialchars($header) . '</th>';
        }
        $lines[] = '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $announcement = $row['announceUrl'] !== ''
                ? sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', htmlspecialchars($row['announceUrl']), htmlspecialchars($row['announceLabel']))
                : '<span class="none">—</span>';

            $orgCell = $row['orgUrl'] === ''
                ? htmlspecialchars($row['org'])
                : sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', htmlspecialchars($row['orgUrl']), htmlspecialchars($row['org']));

            // Same rule as the analyzer table: linked only when the lead
            // differs from the founder, since Founder already links that
            // person and a second link would point back at itself.
            $leadCell = htmlspecialchars($row['lead']);
            if ($row['leadUrl'] !== '') {
                $leadCell = sprintf(
                    '<a href="%s" target="_blank" rel="noopener"%s>%s</a>',
                    htmlspecialchars($row['leadUrl']),
                    $row['leadNote'] === '' ? '' : sprintf(' title="%s"', htmlspecialchars($row['leadNote'])),
                    htmlspecialchars($row['lead']),
                );
            } elseif ($row['leadNote'] !== '') {
                $leadCell = sprintf(
                    '<span class="succession" title="%s">%s</span>',
                    htmlspecialchars($row['leadNote']),
                    htmlspecialchars($row['lead']),
                );
            }

            // The year cell already carries markup, so it takes the note as a
            // wrapper rather than through noted(), which escapes its input.
            // Psalm needs one: its server shipped in a release two months
            // before the release that announced it.
            $initialCell = $this->timeYear($row['initial']);
            if (($row['initialNote'] ?? '') !== '') {
                $initialCell = sprintf(
                    '<span class="noted" title="%s">%s</span>',
                    htmlspecialchars($row['initialNote']),
                    $initialCell,
                );
            }

            $lines[] = '<tr>'
                . sprintf('<th class="tool-name"><a href="%s" target="_blank" rel="noopener">%s</a></th>', htmlspecialchars($row['url']), htmlspecialchars($row['name']))
                . '<td>' . $this->noted($row['diagnostics'], $row['diagnosticsNote'] ?? '') . '</td>'
                . '<td>' . $this->noted($row['analyzers'], $row['analyzersNote'] ?? '') . '</td>'
                . '<td>' . $this->noted($row['bundled'], $row['bundledNote'] ?? '') . '</td>'
                . '<td>' . $this->noted($row['language'], $row['languageNote'] ?? '') . '</td>'
                . '<td>' . $row['founder'] . '</td>'
                . '<td>' . $orgCell . '</td>'
                . '<td>' . $leadCell . '</td>'
                . '<td>' . $this->noted($row['license'], $row['licenseNote'] ?? '') . '</td>'
                . '<td>' . $initialCell . '</td>'
                . '<td>' . $this->timeLatestRelease($row['latest']) . '</td>'
                . '<td>' . $this->noted($row['ast'], $row['astNote'] ?? '') . '</td>'
                . '<td>' . $announcement . '</td>'
                . '</tr>';
        }

        $lines[] = '</tbody></table></div>';

        return $lines;
    }

    /**
     * A cell value, optionally carrying a hover note.
     *
     * Without a note this is plain escaped text, so a cell never picks up the
     * dotted "there is more here" underline unless there really is more.
     */
    private function noted(string $text, string $note): string
    {
        if ($note === '') {
            return htmlspecialchars($text);
        }

        return sprintf(
            '<span class="noted" title="%s">%s</span>',
            htmlspecialchars($note),
            htmlspecialchars($text),
        );
    }

    /**
     * Wrap a calendar year for machine-readable markup.
     *
     * HTML has no &lt;date&gt;; years and dates use &lt;time datetime&gt;.
     */
    private function timeYear(string $year): string
    {
        return sprintf(
            '<time datetime="%s">%s</time>',
            htmlspecialchars($year),
            htmlspecialchars($year),
        );
    }

    /**
     * Render "version (YYYY-MM-DD)" with the date as &lt;time datetime&gt;.
     *
     * Falls back to plain escaped text when the trailing date is missing.
     */
    private function timeLatestRelease(string $latest): string
    {
        if (preg_match('/^(.+?)\s+\((\d{4}-\d{2}-\d{2})\)$/', $latest, $matches) !== 1) {
            return htmlspecialchars($latest);
        }

        return sprintf(
            '%s (<time datetime="%s">%s</time>)',
            htmlspecialchars($matches[1]),
            htmlspecialchars($matches[2]),
            htmlspecialchars($matches[2]),
        );
    }

    /**
     * Render one results matrix for a subset of cases.
     *
     * @param array<string, TestGroup> $testGroups
     * @param list<TestCase> $cases
     * @param list<string> $tools
     * @return list<string>
     */
    private function renderMatrix(
        string $resultsRoot,
        array $testGroups,
        array $cases,
        array $tools,
        bool $style,
    ): array {
        $lines = [];
        $lines[] = '<table>';
        $lines[] = '<thead><tr><th scope="col">Test</th>';

        foreach ($tools as $tool) {
            $lines[] = sprintf(
                '<th scope="col">%s<br><small>%s</small></th>',
                htmlspecialchars($tool),
                $this->versionCell($resultsRoot, $tool),
            );
        }

        $lines[] = '</tr></thead><tbody>';

        foreach ($testGroups as $groupKey => $group) {
            $groupCases = array_values(array_filter(
                $cases,
                static fn (TestCase $testCase): bool => $testCase->groupKey === $groupKey,
            ));

            if ($groupCases === []) {
                continue;
            }

            $lines[] = sprintf(
                '<tr class="group"><td colspan="%d">%s</td></tr>',
                count($tools) + 1,
                htmlspecialchars($group->name),
            );

            foreach ($groupCases as $testCase) {
                [$title] = $this->docblock($testCase);
                $href = self::DETAILS_DIR . '/' . rawurlencode($testCase->name) . '.html';

                $lines[] = sprintf(
                    '<tr id="%s"><td class="test-cell"><a class="test-link" href="%s">%s</a></td>',
                    htmlspecialchars($testCase->name),
                    htmlspecialchars($href),
                    $this->renderInline($title),
                );

                foreach ($tools as $tool) {
                    $result = $this->loadResult($resultsRoot, $tool, $testCase->name);
                    [$display, $class] = $style ? $this->styleStatusOf($result) : $this->statusOf($result);

                    $cell = htmlspecialchars($display);
                    if ($tool === 'phpstan') {
                        // Merge the phpstan-strict column: mark diagnostics that
                        // only the strict-rules config catches.
                        [$display, $class, $suffix] = $this->phpstanMerged($resultsRoot, $testCase->name, $result, $style);
                        $cell = htmlspecialchars($display) . $suffix;
                    } elseif ($tool === 'psalm') {
                        // Merge the pzoom column: mark where the Psalm port differs.
                        [$display, $class, $suffix] = $this->psalmMerged($resultsRoot, $testCase->name, $result, $style);
                        $cell = htmlspecialchars($display) . $suffix;
                    } elseif (!$style) {
                        $cell .= $this->levelSuffix($result);
                    }

                    if (!$style) {
                        $cell .= $this->falsePositiveSuffix($result);
                    }

                    $notes = trim((string) ($result['notes'] ?? ''));
                    if ($notes !== '') {
                        $cell .= sprintf(
                            ' <span class="hover-card"><span class="hover-card__trigger hover-card__notes-label">Notes</span><span class="hover-card__popup">%s</span></span>',
                            $this->renderLinkedText($notes),
                        );
                    }

                    $lines[] = sprintf('<td class="%s"><a class="cell-link" href="%s">%s</a></td>', $class, htmlspecialchars($href), $cell);
                }

                $lines[] = '</tr>';
            }
        }

        $lines[] = '</tbody></table>';

        return $lines;
    }

    /**
     * @param list<string> $tools
     */
    private function renderDetailPage(
        string $resultsRoot,
        TestCase $testCase,
        ?TestGroup $group,
        array $tools,
    ): string {
        [$title, $description] = $this->docblock($testCase);

        $body = [];
        $body[] = sprintf('<p class="crumb"><a href="../%s">&larr; All results</a></p>', self::INDEX_FILE);
        $body[] = sprintf('<h1>%s</h1>', $this->renderInline($title));

        $meta = [];
        if ($group !== null) {
            $meta[] = 'Group: ' . htmlspecialchars($group->name);
            $meta[] = 'Category: ' . htmlspecialchars($group->sourceCategory);
        }
        $meta[] = 'File: ' . htmlspecialchars($testCase->fileName);
        if ($this->testKind($testCase) === 'style') {
            $meta[] = 'Kind: <strong>Style / opinionated</strong> (no runtime-safety impact)';
        }
        $body[] = '<p class="meta">' . implode(' &middot; ', $meta) . '</p>';

        if ($description !== '') {
            $body[] = '<div class="doc">' . $this->renderMultiline($description) . '</div>';
        }

        $body[] = '<h2>Analyzer results</h2>';
        $body[] = '<table class="detail-results">';
        $body[] = '<thead><tr><th scope="col">Analyzer</th><th scope="col">Version</th><th scope="col">Result</th><th scope="col">Diagnostics</th></tr></thead><tbody>';

        foreach ($tools as $tool) {
            $result = $this->loadResult($resultsRoot, $tool, $testCase->name);
            [$display, $class] = $this->statusOf($result);
            $status = htmlspecialchars($display) . $this->levelSuffix($result) . $this->falsePositiveSuffix($result);

            $output = trim((string) ($result['output'] ?? ''));
            $errorsDiff = trim((string) ($result['errors_diff'] ?? ''));
            $notes = trim((string) ($result['notes'] ?? ''));

            // The phpstan row also carries strict-rules-only diagnostics; the
            // psalm row carries pzoom's (Psalm port) diagnostics where they differ.
            $mergeExtra = '';
            if ($tool === 'phpstan') {
                [, , $suffix] = $this->phpstanMerged($resultsRoot, $testCase->name, $result, false);
                $status = htmlspecialchars($display) . ($output !== '' ? $this->levelSuffix($result) : $suffix);
                $strictOutput = trim((string) ($this->loadResult($resultsRoot, 'phpstan-strict', $testCase->name)['output'] ?? ''));
                if ($strictOutput !== '' && $strictOutput !== $output) {
                    $mergeExtra = '<details' . ($output === '' ? ' open' : '') . '><summary>With strict-rules</summary><pre class="diag">'
                        . htmlspecialchars($strictOutput) . '</pre></details>';
                }
            } elseif ($tool === 'psalm') {
                [, , $suffix] = $this->psalmMerged($resultsRoot, $testCase->name, $result, false);
                $status = htmlspecialchars($display) . $suffix;
                if ($suffix !== '') {
                    $pzoomOutput = trim((string) ($this->loadResult($resultsRoot, 'pzoom', $testCase->name)['output'] ?? ''));
                    $mergeExtra = '<details' . ($output === '' ? ' open' : '') . '><summary>pzoom (Psalm port)</summary>'
                        . ($pzoomOutput !== '' ? '<pre class="diag">' . htmlspecialchars($pzoomOutput) . '</pre>' : '<p class="none">No diagnostics from pzoom.</p>')
                        . '</details>';
                }
            }

            $diagnostics = '';
            if ($output !== '') {
                $diagnostics .= '<pre class="diag">' . htmlspecialchars($output) . '</pre>';
            } elseif ($mergeExtra === '') {
                $diagnostics .= '<p class="none">No diagnostics reported.</p>';
            }
            $diagnostics .= $mergeExtra;
            $diagnostics .= $this->typeHandlingBreakdown($result);
            if ($errorsDiff !== '') {
                $diagnostics .= '<details><summary>Expectation diff</summary><pre class="diag">' . htmlspecialchars($errorsDiff) . '</pre></details>';
            }
            if ($notes !== '') {
                $diagnostics .= '<p class="note-line"><strong>Notes:</strong> ' . $this->renderLinkedText($notes) . '</p>';
            }

            $body[] = sprintf(
                '<tr><th class="tool-name">%s</th><td><small>%s</small></td><td class="%s status-cell">%s</td><td class="diag-cell">%s</td></tr>',
                htmlspecialchars($tool),
                $this->versionCell($resultsRoot, $tool),
                $class,
                $status,
                $diagnostics,
            );
        }

        $body[] = '</tbody></table>';

        $body[] = '<h2>Source</h2>';
        $body[] = '<pre class="source"><code>' . htmlspecialchars($this->readSource($testCase->path)) . '</code></pre>';

        foreach ($testCase->supportPaths as $supportPath) {
            $body[] = sprintf('<h2>Support: <code>%s</code></h2>', htmlspecialchars(basename($supportPath)));
            $body[] = '<pre class="source"><code>' . htmlspecialchars($this->readSource($supportPath)) . '</code></pre>';
        }

        return $this->renderPage($title . ' — Conformance', $body, true);
    }

    /**
     * Wrap body lines in the shared page skeleton.
     *
     * @param list<string> $body
     */
    private function renderPage(string $title, array $body, bool $isDetail): string
    {
        $html = [];
        $html[] = '<!DOCTYPE html>';
        $html[] = '<html lang="en">';
        $html[] = '<head>';
        $html[] = '  <meta charset="UTF-8">';
        $html[] = '  <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html[] = sprintf('  <title>%s</title>', htmlspecialchars($title));
        // Detail pages sit one directory deeper than the index.
        $html[] = sprintf(
            '  <link rel="stylesheet" href="%s%s">',
            $isDetail ? '../' : '',
            self::STYLESHEET_FILE,
        );
        $html[] = '</head>';
        $html[] = '<body class="' . ($isDetail ? 'detail' : 'index') . '">';
        foreach ($body as $line) {
            $html[] = $line;
        }
        if ($isDetail) {
            $html[] = $this->highlightScript();
        }
        $html[] = '</body></html>';

        return implode("\n", $html);
    }

    /**
     * Client-side syntax highlighting for the source blocks via Shiki (CDN).
     *
     * @see https://shiki.style/guide/install#cdn-usage
     */
    private function highlightScript(): string
    {
        return <<<'HTML'
<script type="module">
  import { codeToHtml } from 'https://esm.sh/shiki@4.3.1';

  for (const code of document.querySelectorAll('pre.source > code')) {
    const pre = code.closest('pre.source');
    try {
      pre.outerHTML = await codeToHtml(code.textContent, { lang: 'php', theme: 'github-dark' });
    } catch (error) {
      console.error('shiki highlight failed', error);
    }
  }
</script>
HTML;
    }

    /**
     * Copy the stylesheet next to the generated pages.
     *
     * The report is a directory of ~100 detail pages plus an index; a linked
     * stylesheet keeps one copy of it instead of inlining the same block into
     * every page, and keeps the CSS editable as CSS.
     */
    private function copyStylesheet(string $resultsRoot): void
    {
        $destination = $resultsRoot . DIRECTORY_SEPARATOR . self::STYLESHEET_FILE;

        if (!copy(self::STYLESHEET_SOURCE, $destination)) {
            throw new RuntimeException(sprintf('Failed to write stylesheet: %s', $destination));
        }
    }

    /**
     * Extract a human-readable title and description from the leading docblock.
     *
     * @return array{0: string, 1: string} [title, description]
     */
    private function docblock(TestCase $testCase): array
    {
        $source = $this->readSource($testCase->path);

        if (!preg_match('#/\*\*(.*?)\*/#s', $source, $matches)) {
            return [$this->humanize($testCase->name), ''];
        }

        $lines = [];
        foreach (explode("\n", $matches[1]) as $rawLine) {
            $lines[] = preg_replace('/^\s*\*\s?/', '', $rawLine) ?? $rawLine;
        }

        // First blank-separated paragraph becomes the title; the rest is the description.
        $title = [];
        $rest = [];
        $inTitle = true;
        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Metadata tags are not part of the human-facing description.
            if (str_starts_with($trimmed, '@conformance-kind')) {
                continue;
            }
            if ($inTitle) {
                if ($trimmed === '') {
                    if ($title !== []) {
                        $inTitle = false;
                    }
                    continue;
                }
                $title[] = $trimmed;
                continue;
            }
            $rest[] = $line;
        }

        $titleText = trim(implode(' ', $title));
        if ($titleText === '') {
            $titleText = $this->humanize($testCase->name);
        }

        // Headings read better without the sentence-ending period (but keep an
        // ellipsis intact).
        if (str_ends_with($titleText, '.') && !str_ends_with($titleText, '..')) {
            $titleText = substr($titleText, 0, -1);
        }

        return [$titleText, rtrim(implode("\n", $rest))];
    }

    private function humanize(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name));
    }

    private function readSource(string $path): string
    {
        $contents = @file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    /**
     * Classify a test as a soundness check (runtime-safety) or a style check
     * (opinionated/advisory), via the optional `@conformance-kind` docblock tag.
     */
    private function testKind(TestCase $testCase): string
    {
        if (preg_match('/@conformance-kind:?\s+([\w-]+)/', $this->readSource($testCase->path), $matches) === 1) {
            return strtolower($matches[1]);
        }

        return 'soundness';
    }

    /**
     * @param array<string, mixed> $result
     * @return array{0: string, 1: string} [display, cssClass]
     */
    private function statusOf(array $result): array
    {
        // `// T`-marked tests answer a different question than Pass/Fail, and
        // answer it on two axes. See typeHandlingOf().
        if (isset($result['recognition'])) {
            return $this->typeHandlingOf($result);
        }

        // Some analyzers decline to report a diagnostic the test expects and
        // have said so upstream. That is a curated fact, not something the
        // harness can derive, so it still overrides the verdict word.
        if ((string) ($result['status'] ?? 'Unknown') === 'By design') {
            return ['Not reported (by design)', 'by-design'];
        }

        $display = (string) ($result['conformance_automated'] ?? 'Unknown');

        $class = match ($display) {
            'Pass' => 'pass',
            'Fail' => 'fail',
            default => 'unknown',
        };

        return [$display, $class];
    }

    /**
     * Cell text for a type-handling test.
     *
     * Recognition ("does the analyzer resolve this spelling?") and enforcement
     * ("does it then reject the excluded values?") are derived separately, so
     * the cell can no longer blur the two the way a single "Full support" did.
     * Only the reason a recognized type is *not* enforced stays hand-curated:
     * the base type it widened to, or an upstream decision not to report.
     *
     * @param array<string, mixed> $result
     * @return array{0: string, 1: string} [display, cssClass]
     */
    private function typeHandlingOf(array $result): array
    {
        if ((string) $result['recognition'] === 'unrecognized') {
            return ['Unrecognized', 'not-supported'];
        }

        $enforcement = (string) ($result['enforcement'] ?? '');

        if ($enforcement === 'enforced') {
            return ['Enforced', 'pass'];
        }

        if ($enforcement === 'partial') {
            return [
                sprintf('Partly enforced (%s)', (string) ($result['enforced_lines'] ?? '')),
                'partial',
            ];
        }

        $status = (string) ($result['status'] ?? 'Unknown');

        // "Falls back to <base>" carries the base type name, so match by prefix.
        if (str_starts_with($status, 'Falls back to ')) {
            return ['Widened to ' . substr($status, strlen('Falls back to ')), 'falls-back'];
        }

        if ($status === 'By design') {
            return ['Not enforced (by design)', 'by-design'];
        }

        return ['Not enforced', 'falls-back'];
    }

    /**
     * Spell out the two facets line by line on the detail page, so a `Partly
     * enforced` or `Unrecognized` cell can be traced back to the exact
     * declarations and call sites behind it.
     *
     * @param array<string, mixed> $result
     */
    private function typeHandlingBreakdown(array $result): string
    {
        if (!isset($result['recognition'])) {
            return '';
        }

        $unrecognized = $result['unrecognized_lines'] ?? [];
        $falsePositives = $result['false_positive_lines'] ?? [];

        $items = [];
        $items[] = sprintf(
            '<li><strong>Recognition:</strong> %s</li>',
            is_array($unrecognized) && $unrecognized !== []
                ? htmlspecialchars(sprintf(
                    'spelling not resolved — reported on declaration line(s) %s',
                    implode(', ', $unrecognized),
                ))
                : 'spelling resolved',
        );
        $items[] = sprintf(
            '<li><strong>Enforcement:</strong> %s of the expected violations reported%s</li>',
            htmlspecialchars((string) ($result['enforced_lines'] ?? '')),
            // An unresolved spelling often rejects every value, valid ones
            // included, so hits on the violating lines are not enforcement.
            is_array($unrecognized) && $unrecognized !== []
                ? ' &mdash; incidental, since the spelling was not resolved'
                : '',
        );

        if (is_array($falsePositives) && $falsePositives !== []) {
            $items[] = sprintf(
                '<li><strong>False positives:</strong> %s</li>',
                htmlspecialchars(sprintf('line(s) %s', implode(', ', $falsePositives))),
            );
        }

        return '<ul class="facets">' . implode('', $items) . '</ul>';
    }

    /**
     * Diagnostics on lines the test neither expects nor marks as a type
     * declaration. On a type-handling test these are the analyzer's own false
     * positives - Phan resolving `number` as a class name and then rejecting
     * `1` for it, say - and the two-axis label alone would hide them, so they
     * get their own tag.
     *
     * @param array<string, mixed> $result
     */
    private function falsePositiveSuffix(array $result): string
    {
        $lines = $result['false_positive_lines'] ?? null;
        if (!is_array($lines) || $lines === []) {
            return '';
        }

        return sprintf(
            ' <small class="fp-tag" title="%s">&#9888; %d false positive%s</small>',
            htmlspecialchars(sprintf('Unexpected diagnostics on line(s) %s', implode(', ', $lines))),
            count($lines),
            count($lines) === 1 ? '' : 's',
        );
    }

    /**
     * For style/opinionated checks a pass/fail verdict is not meaningful, so
     * report only whether each analyzer opts into flagging the rule.
     *
     * @param array<string, mixed> $result
     * @return array{0: string, 1: string} [display, cssClass]
     */
    private function styleStatusOf(array $result): array
    {
        $output = trim((string) ($result['output'] ?? ''));

        return $output !== '' ? ['Reported', 'reported'] : ['—', 'muted'];
    }

    /**
     * The lowest PHPStan level whose rules report the diagnostic under test.
     *
     * This is a *rule* threshold, not a support tier: PHPStan resolves types
     * identically at every level, so the number says which rule set has to be
     * switched on before the analyzer speaks up — never how well a type is
     * modelled.
     *
     * @param array<string, mixed> $result
     */
    private function levelSuffix(array $result): string
    {
        $level = $result['expected_diagnostic_level'] ?? null;
        if (!is_int($level)) {
            return '';
        }

        $levelLabel = $level === 10
            ? 'reported Lv.max'
            : sprintf('reported Lv.%d+', $level);

        // The verb carries the meaning the bare "(Lv 5+)" used to lose: the
        // level is where the diagnostic starts being *reported*, not where
        // support for the type starts.
        return sprintf(
            ' <small class="level-tag" title="%s">%s</small>',
            htmlspecialchars(sprintf(
                'Reported from PHPStan level %s upwards. Levels enable rule sets; type inference itself is level-independent.',
                $level === 10 ? 'max' : (string) $level,
            )),
            htmlspecialchars($levelLabel),
        );
    }

    /**
     * Merge the phpstan and phpstan-strict columns into one. The standard
     * config drives the status and level; when a diagnostic is only caught by
     * the strict-rules config, the cell is tagged "(strict)".
     *
     * @param array<string, mixed> $result phpstan (non-strict) result
     * @return array{0: string, 1: string, 2: string} [display, cssClass, suffix]
     */
    private function phpstanMerged(string $resultsRoot, string $testName, array $result, bool $style): array
    {
        $strict = $this->loadResult($resultsRoot, 'phpstan-strict', $testName);
        $stdOutput = trim((string) ($result['output'] ?? ''));
        $strictOutput = trim((string) ($strict['output'] ?? ''));
        $strictOnly = $strictOutput !== '' && $stdOutput === '';
        $strictTag = ' <small>(strict)</small>';

        if ($style) {
            $reported = $stdOutput !== '' || $strictOutput !== '';
            [$display, $class] = $reported ? ['Reported', 'reported'] : ['—', 'muted'];

            return [$display, $class, $strictOnly ? $strictTag : ''];
        }

        [$display, $class] = $this->statusOf($result);

        if ($stdOutput !== '') {
            return [$display, $class, $this->levelSuffix($result)];
        }

        if ($strictOnly) {
            return [$display, $class, $strictTag];
        }

        return [$display, $class, ''];
    }

    /**
     * Fold the pzoom column into psalm. pzoom is a Psalm port, so its status
     * drives nothing; the psalm cell is only tagged "(pzoom≠)" where pzoom flags
     * a different set of lines than Psalm.
     *
     * @param array<string, mixed> $result psalm result
     * @return array{0: string, 1: string, 2: string} [display, cssClass, suffix]
     */
    private function psalmMerged(string $resultsRoot, string $testName, array $result, bool $style): array
    {
        $pzoom = $this->loadResult($resultsRoot, 'pzoom', $testName);
        [$display, $class] = $style ? $this->styleStatusOf($result) : $this->statusOf($result);

        // Only compare when pzoom actually ran for this test.
        $differs = $pzoom !== []
            && $this->diagnosticLineSet($result) !== $this->diagnosticLineSet($pzoom);

        return [$display, $class, $differs ? ' <small>(pzoom&ne;)</small>' : ''];
    }

    /**
     * The set of source lines a result flagged, as a stable comma-joined key.
     *
     * @param array<string, mixed> $result
     */
    private function diagnosticLineSet(array $result): string
    {
        $lines = [];
        foreach (explode("\n", (string) ($result['output'] ?? '')) as $line) {
            if (preg_match('/^[^:]+:(\d+):/', $line, $matches) === 1) {
                $lines[(int) $matches[1]] = true;
            }
        }

        $keys = array_keys($lines);
        sort($keys);

        return implode(',', $keys);
    }

    private function versionCell(string $resultsRoot, string $tool): string
    {
        $fullVersion = $this->loadVersion($resultsRoot, $tool);
        $shortVersion = $this->shortVersion($tool, $fullVersion);
        $releaseUrl = $this->releaseUrl($tool, $shortVersion);
        $versionHtml = htmlspecialchars($shortVersion);
        $popupHtml = htmlspecialchars(str_replace("\n", ' ', $fullVersion));

        if ($releaseUrl !== null) {
            $versionHtml = sprintf(
                '<a href="%s" class="hover-card__trigger" target="_blank" rel="noopener">%s</a>',
                htmlspecialchars($releaseUrl),
                $versionHtml,
            );
        } else {
            $versionHtml = sprintf('<span class="hover-card__trigger">%s</span>', $versionHtml);
        }

        return sprintf(
            '<span class="hover-card">%s<span class="hover-card__popup">%s</span></span>',
            $versionHtml,
            $popupHtml,
        );
    }

    private function prepareDetailsDir(string $detailsDir): void
    {
        if (!is_dir($detailsDir) && !mkdir($detailsDir, 0o755, true) && !is_dir($detailsDir)) {
            throw new RuntimeException(sprintf('Failed to create details directory: %s', $detailsDir));
        }

        // Drop stale detail pages so removed tests do not linger.
        foreach (glob($detailsDir . DIRECTORY_SEPARATOR . '*.html') ?: [] as $stale) {
            @unlink($stale);
        }
    }

    private function loadVersion(string $resultsRoot, string $tool): string
    {
        $path = $resultsRoot . DIRECTORY_SEPARATOR . $tool . DIRECTORY_SEPARATOR . 'version.toml';
        if (!is_file($path)) {
            return 'unknown';
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return 'unknown';
        }

        $data = Toml::parseToArray($contents);

        return (string) ($data['version'] ?? 'unknown');
    }

    private function shortVersion(string $tool, string $fullVersion): string
    {
        $version = trim($fullVersion);

        return match ($tool) {
            'phpstan' => $this->extractVersion($version, '/(\d+\.\d+\.\d+)$/'),
            'phpstan-strict' => $this->extractVersion($version, '/(\d+\.\d+\.\d+)$/'),
            'psalm' => $this->extractVersion($version, '/Psalm\s+(\d+\.\d+\.\d+)/'),
            'mago' => $this->extractVersion($version, '/mago\s+(\d+\.\d+\.\d+)/i'),
            'mir' => $this->extractVersion($version, '/mir\s+(\d+\.\d+\.\d+)/i'),
            'intelephense' => $this->extractVersion($version, '/intelephense\s+(\d+\.\d+\.\d+)/i'),
            'steins' => $this->extractVersion($version, '/steins\s+(\d+\.\d+\.\d+)/i'),
            'phan' => $this->extractVersion($version, '/Phan\s+(\d+\.\d+\.\d+)/'),
            'noverify' => $this->extractVersion($version, '/version\s+(\d+\.\d+\.\d+)/i'),
            default => $version,
        };
    }

    private function releaseUrl(string $tool, string $shortVersion): ?string
    {
        if ($shortVersion === 'unknown') {
            return null;
        }

        return match ($tool) {
            'phpstan' => sprintf('https://github.com/phpstan/phpstan/releases/tag/%s', $shortVersion),
            'phpstan-strict' => sprintf('https://github.com/phpstan/phpstan/releases/tag/%s', $shortVersion),
            'psalm' => sprintf('https://github.com/vimeo/psalm/releases/tag/%s', $shortVersion),
            'mago' => sprintf('https://github.com/carthage-software/mago/releases/tag/%s', $shortVersion),
            'mir' => sprintf('https://github.com/jorgsowa/mir/releases/tag/v%s', $shortVersion),
            'intelephense' => sprintf('https://www.npmjs.com/package/intelephense/v/%s', $shortVersion),
            'steins' => sprintf('https://github.com/rigortype/steins/releases/tag/v%s', $shortVersion),
            'phan' => sprintf('https://github.com/phan/phan/releases/tag/%s', $shortVersion),
            'noverify' => sprintf('https://github.com/VKCOM/noverify/releases/tag/v%s', $shortVersion),
            default => null,
        };
    }

    private function extractVersion(string $version, string $pattern): string
    {
        if (preg_match($pattern, $version, $matches) === 1) {
            return $matches[1];
        }

        return $version;
    }

    /**
     * Escape text, then turn `code` spans into <code> and linkify bare URLs.
     */
    private function renderInline(string $text): string
    {
        $escaped = htmlspecialchars($text);

        $escaped = preg_replace_callback(
            '/`([^`]+)`/',
            static fn (array $matches): string => '<code>' . $matches[1] . '</code>',
            $escaped,
        ) ?? $escaped;

        return $this->linkify($escaped);
    }

    private function renderMultiline(string $text): string
    {
        return $this->renderInline($text);
    }

    private function renderLinkedText(string $text): string
    {
        return $this->linkify(htmlspecialchars($text));
    }

    private function linkify(string $escaped): string
    {
        $linked = preg_replace_callback(
            '/https?:\/\/[^\s<]+/i',
            static function (array $matches): string {
                $url = $matches[0];

                // Keep trailing sentence punctuation out of the link target.
                $trailing = '';
                while ($url !== '' && str_contains('.,;:)]}', substr($url, -1))) {
                    $trailing = substr($url, -1) . $trailing;
                    $url = substr($url, 0, -1);
                }

                // Shorten GitHub issue/PR links to `<repo>#<number>`.
                $label = $url;
                if (preg_match('~^https?://github\.com/[^/\s]+/([^/\s]+)/(?:issues|pull)/(\d+)(?:[/#?][^\s<]*)?$~i', $url, $ref) === 1) {
                    $label = htmlspecialchars($ref[1] . '#' . $ref[2]);
                }

                return sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>%s',
                    $url,
                    $label,
                    $trailing,
                );
            },
            $escaped,
        );

        return $linked ?? $escaped;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadResult(string $resultsRoot, string $tool, string $testName): array
    {
        $path = $resultsRoot . DIRECTORY_SEPARATOR . $tool . DIRECTORY_SEPARATOR . $testName . '.toml';
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        return Toml::parseToArray($contents);
    }
}
