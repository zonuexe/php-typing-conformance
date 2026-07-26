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
        //   development. Usually the founder; `leadNote` is set only for rows
        //   where it verifiably is not, and renders as a hover note rather
        //   than silently repeating the founder's name. Phan's is Rasmus
        //   Lerdorf, not Tyson Andre: every one of the last 10 GitHub releases
        //   (5.5.2 through 6.0.7) was cut by rlerdorf, and Andre has no recent
        //   commits or releases despite being widely cited as "the" current
        //   maintainer.
        //
        // [name, expansion, homepage, analysis, interface, bundled, language,
        //  founder(raw HTML), backing, backingUrl, lead, leadNote, license,
        //  initial, latest, ast, announceUrl, announceLabel]
        $rows = [
            ['Phan', '', 'https://github.com/phan/phan', 'Type checker', 'CLI, LSP', 'Fixer (narrow)', 'PHP', 'Rasmus Lerdorf &amp; Andrew Morrison (Etsy)', 'Community (phan)', 'https://github.com/phan', 'Rasmus Lerdorf', 'Every one of the last 10 GitHub releases (5.5.2 through 6.0.7) was cut by rlerdorf; Tyson Andre has no recent commits or releases despite being widely cited elsewhere as the current maintainer.', 'MIT', '2015', '6.0.7 (2026-06-22)', 'ext-ast / tolerant-php-parser', 'https://talks.php.net/ph16', 'Deploying PHP 7 (talk)'],
            ['Psalm', 'Also referred to as a “PHP Static Analysis Linting Machine”', 'https://psalm.dev', 'Type checker', 'CLI, LSP', 'Fixer, refactorer', 'PHP', 'Matt Brown (Vimeo)', 'Community (psalm)', 'https://github.com/psalm', 'Daniil Gentili', 'Sole active maintainer since Vimeo stepped back; the repository still lives under the vimeo/psalm GitHub org.', 'MIT', '2016', '6.16.1 (2026-03-19)', 'nikic/PHP-Parser', 'https://medium.com/vimeo-engineering-blog/automated-type-inference-for-dynamically-typed-programs-6e79197e5420', 'Automated type inference'],
            ['PHPStan', 'PHP Static Analysis Tool', 'https://phpstan.org', 'Type checker', 'CLI', '—', 'PHP', 'Ondřej Mirtes', 'PHPStan s.r.o.', 'https://github.com/phpstan', 'Ondřej Mirtes', '', 'MIT', '2016', '2.2.5 (2026-07-05)', 'nikic/PHP-Parser', 'https://phpstan.org/blog/find-bugs-in-your-code-without-writing-tests', 'Find Bugs Without Tests'],
            ['Intelephense', '', 'https://intelephense.com', 'Code intelligence', 'LSP', 'Formatter, rename', 'TypeScript', 'Ben Mewburn', 'Intelephense', 'https://intelephense.com', 'Ben Mewburn', '', 'Proprietary (freemium)', '2017', '1.18.5 (2026-06-21)', 'own parser', '', ''],
            ['NoVerify', '', 'https://github.com/VKCOM/noverify', 'Type-aware linter', 'CLI', '—', 'Go', 'VK (VKCOM)', 'VK.COM', 'https://github.com/VKCOM', '—', '', 'MIT', '2019', '0.5.5 (2025-04-22)', 'VKCOM/php-parser', 'https://habr.com/ru/companies/vk/articles/442284/', 'VK open-sources it (Habr)'],
            ['Mago', '', 'https://mago.carthage.software', 'Type checker', 'CLI', 'Linter, formatter, arch guard', 'Rust', 'Saif Eddin Gmati (Carthage Software)', 'Carthage Software', 'https://github.com/carthage-software', 'Saif Eddin Gmati', '', 'MIT OR Apache-2.0', '2024', '1.44.0 (2026-07-18)', 'own parser', 'https://github.com/carthage-software/mago/releases/tag/1.0.0', 'Mago 1.0.0'],
            ['mir', '', 'https://github.com/jorgsowa/mir', 'Type checker', 'CLI', '—', 'Rust', 'Jorg Sowa', 'Personal', '', 'Jorg Sowa', '', 'MIT', '2026', '0.60.0 (2026-07-18)', 'own (php-rs-parser)', '', ''],
            ['Pzoom', '', 'https://pzoom.dev', 'Type checker', 'CLI', '—', 'Rust', 'Matt Brown (muglug)', 'Personal', '', 'Matt Brown', '', 'MIT', '2026', 'unversioned (2026-06-24)', 'Mago parser', 'https://mattbrown.dev/articles/from-psalm-to-pzoom', 'From Psalm to Pzoom'],
            ['PHP;STEINS', '', 'https://github.com/rigortype/steins', 'Type checker', 'CLI', 'Annotator, transforms', 'Rust', 'USAMI Kenta (rigortype)', 'TypedDuck', 'https://github.com/rigortype', 'USAMI Kenta', '', 'Apache-2.0', '2026', '0.1.0 (2026-07-24)', 'Mago parser (fork)', '', ''],
        ];

        $headers = ['Analyzer', 'Analysis', 'Interface', 'Bundled with it', 'Language', 'Founder', 'Organization', 'Lead maintainer', 'License', 'Initial release', 'Latest release', 'AST / parser', 'Release announcement'];

        $lines = [];
        $lines[] = '<h2 class="section">Analyzers</h2>';
        $lines[] = '<p class="section-note">Reference metadata for each analyzer compared above. Versions are those pinned by this suite. Every tool here is a static analyzer &mdash; that is what qualifies it for the matrix, not what tells it apart. Three independent axes do:</p>';
        $lines[] = '<ul class="axis-list">'
            . '<li><strong>Analysis</strong> &mdash; what the tool is trying to establish. '
            . 'A <em>type checker</em> aims at type correctness: whole-program inference, generics, narrowing, and diagnostics that follow from the type system. '
            . 'A <em>type-aware linter</em> aims at a rule catalogue and carries inference to make those rules sharper. '
            . '<em>Code intelligence</em> infers types mainly to drive completion, hover and navigation; diagnostics are one feature among many.</li>'
            . '<li><strong>Interface</strong> &mdash; how you talk to it. CLI, the Language Server Protocol, or both. Independent of the analysis: Phan and Psalm ship the same engine behind either.</li>'
            . '<li><strong>Bundled with it</strong> &mdash; what else lives in the same distribution. A formatter or a fixer next to the checker says nothing about how the checker reasons; Mago calls itself a toolchain, but its <code>analyze</code> command is a type checker like the rest.</li>'
            . '</ul>';
        $lines[] = '<p class="section-note">Founder / Organization / Lead maintainer are three more independent questions, kept apart for the same reason: <strong>Founder</strong> is who started it. <strong>Organization</strong> is the entity currently behind it &mdash; often the founder&rsquo;s own company or GitHub org rather than an outside adopter, and not necessarily a financial backer: a linked <em>Community (org)</em> marks a project with no company at all, just a shared GitHub home, and <em>Personal</em> marks one with no separate entity of any kind, rather than leaving either as an ambiguous &ldquo;&mdash;&rdquo;. <strong>Lead maintainer</strong> is the individual currently driving day-to-day development; usually the founder, but a hover note marks the rows where it verifiably is not.</p>';
        $lines[] = '<div class="table-scroll"><table class="analyzer-meta">';
        $lines[] = '<thead><tr>';
        foreach ($headers as $header) {
            $lines[] = '<th scope="col">' . htmlspecialchars($header) . '</th>';
        }
        $lines[] = '</tr></thead><tbody>';

        foreach ($rows as [$name, $expansion, $url, $analysis, $interface, $bundled, $language, $founder, $backing, $backingUrl, $lead, $leadNote, $license, $initial, $latest, $ast, $announceUrl, $announceLabel]) {
            $announcement = $announceUrl !== ''
                ? sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', htmlspecialchars($announceUrl), htmlspecialchars($announceLabel))
                : '<span class="none">—</span>';

            $label = $expansion === ''
                ? htmlspecialchars($name)
                : sprintf('<abbr title="%s">%s</abbr>', htmlspecialchars($expansion), htmlspecialchars($name));

            $backingCell = $backingUrl === ''
                ? htmlspecialchars($backing)
                : sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', htmlspecialchars($backingUrl), htmlspecialchars($backing));

            $leadCell = $leadNote === ''
                ? htmlspecialchars($lead)
                : sprintf(
                    '<span class="succession" title="%s">%s</span>',
                    htmlspecialchars($leadNote),
                    htmlspecialchars($lead),
                );

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
        $html[] = '  <style>';
        $html[] = $this->styles();
        $html[] = '  </style>';
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

    private function styles(): string
    {
        return <<<'CSS'
body { font-family: system-ui, sans-serif; margin: 24px; color: #1b1b1b; background: #ffffff; }
body.detail { max-width: 960px; }
a { color: #0b62c4; }
h1 { margin-bottom: 0.2em; }
p.lead { color: #555; margin-top: 0; }
h2.section { margin-top: 2em; border-bottom: 2px solid #e5e7eb; padding-bottom: 4px; }
p.section-note { color: #555; font-size: 0.9em; margin-top: 0.4em; max-width: 70ch; }
.table-scroll { overflow-x: auto; }
table.analyzer-meta a { text-decoration: none; font-weight: 600; }
p.crumb { margin: 0 0 12px; }
p.meta { color: #555; font-size: 0.9em; margin-top: 0; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
th[scope="col"], td { font-size: 0.9em; }
thead th { position: sticky; top: 0; background-color: white; z-index: 1; }
small a { color: inherit; }
.test-cell { min-width: 260px; }
.test-link { font-weight: 600; text-decoration: none; }
.test-link:hover { text-decoration: underline; }
tr[id]:target { outline: 2px solid #0b62c4; outline-offset: -2px; }
.cell-link { display: block; color: inherit; text-decoration: none; }
.hover-card { position: relative; display: inline-flex; align-items: center; gap: 0.35rem; }
.hover-card__trigger { cursor: help; }
.hover-card__popup { position: absolute; left: 0; top: calc(100% + 6px); z-index: 10; min-width: 220px; max-width: 360px; padding: 8px 10px; border-radius: 8px; background: rgba(20, 20, 20, 0.96); color: #fff; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.22); font-size: 0.875em; line-height: 1.4; visibility: hidden; opacity: 0; transform: translateY(-2px); transition: opacity 120ms ease, transform 120ms ease, visibility 120ms ease; }
.hover-card:hover .hover-card__popup, .hover-card:focus-within .hover-card__popup { visibility: visible; opacity: 1; transform: translateY(0); }
.hover-card__popup a { color: #9fd0ff; }
.hover-card__notes-label { border-bottom: 1px dotted currentColor; font-size: 0.875em; }
.group { background: #f3f3f3; font-weight: 700; }
.pass { background: #dff7df; }
.fail { background: #f9d6d6; }
.by-design { background: #f6e7c8; }
.not-supported { background: #f4f4f5; color: #9a9a9a; font-style: italic; }
.falls-back { background: #e4eefb; color: #2f5c9e; }
.unknown { background: #f0f0f0; }
.reported { background: #dbe7fb; }
.muted { background: #f6f6f6; color: #999; }
.level-tag { color: #666; font-weight: 400; cursor: help; }
.fp-tag { display: block; color: #a33; font-weight: 600; cursor: help; }
.partial { background: #fdf0cf; }
.facets { margin: 8px 0 0; padding-left: 18px; font-size: 0.85em; color: #555; }
.legend-facets { margin: 4px 0 10px; padding-left: 20px; font-size: 0.9em; }
.axis-list { margin: 4px 0 14px; padding-left: 20px; font-size: 0.9em; line-height: 1.5; max-width: 70em; }
.analyzer-meta abbr[title] { text-decoration: underline dotted; text-underline-offset: 2px; cursor: help; }
.analyzer-meta .succession[title] { text-decoration: underline dotted; text-underline-offset: 2px; cursor: help; }
.legend { margin: 16px 0 24px; border: 1px solid #ddd; border-radius: 6px; padding: 10px 14px; background: #fbfbfb; }
.legend > summary { cursor: pointer; font-weight: 600; }
.legend-intro { margin: 14px 0 6px; font-size: 0.9em; }
.legend-list { display: grid; grid-template-columns: max-content 1fr; gap: 6px 14px; margin: 0; font-size: 0.9em; }
.legend-list dt, .legend-list dd { margin: 0; }
.legend-swatch { display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: 600; white-space: nowrap; }
.legend-swatch.plain { background: none; padding: 2px 0; font-weight: 400; }
.doc { white-space: pre-wrap; background: #fafafa; border: 1px solid #eee; border-left: 3px solid #0b62c4; border-radius: 4px; padding: 10px 14px; margin: 12px 0 20px; line-height: 1.5; }
.doc code, .status-cell code, h1 code { background: #eef1f4; padding: 0.1em 0.35em; border-radius: 3px; font-size: 0.9em; }
.detail-results th.tool-name { width: 110px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.status-cell { white-space: nowrap; font-weight: 600; }
.diag-cell { width: 100%; }
pre.diag { white-space: pre-wrap; word-break: break-word; margin: 0; font-size: 0.85em; }
pre.source, pre.shiki { padding: 14px 16px; border-radius: 6px; overflow-x: auto; font-size: 0.85em; line-height: 1.45; }
pre.source { background: #1e1e1e; color: #eaeaea; }
.none { color: #888; margin: 0; font-style: italic; }
.note-line { font-size: 0.85em; margin: 6px 0 0; }
details summary { cursor: pointer; font-size: 0.85em; color: #555; }
CSS;
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
