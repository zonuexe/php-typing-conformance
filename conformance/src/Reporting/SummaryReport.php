<?php

declare(strict_types=1);

namespace Conformance\Reporting;

use Conformance\Discovery\TestCase;
use Conformance\Metadata\AnalyzerMetadata;
use Conformance\TestGroup\TestGroup;
use Internal\Toml\Toml;
use RuntimeException;
use function htmlspecialchars;
use function preg_match;
use function sprintf;
use function trim;

/**
 * Generate the HTML report: one index page plus a detail page per test.
 *
 * This class decides *what* each page says — which verdict vocabulary a cell
 * uses, which columns are merged, which curated metadata the reference tables
 * carry. The markup itself lives in templates/*.phtml, which receive plain
 * data and nothing else; they escape it with the h*() helpers in
 * src/functions.php.
 */
final class SummaryReport
{
    private const DETAILS_DIR = 'tests';
    private const INDEX_FILE = 'results.html';
    private const STYLESHEET_FILE = 'report.css';

    /**
     * @param list<AnalyzerMetadata> $analyzers ordered as the analyzer table shows them
     */
    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly array $analyzers,
    ) {
    }

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

        $body = $this->render('index.phtml', [
            'legend' => $this->render('legend.phtml'),
            'soundnessMatrix' => $this->renderMatrix($resultsRoot, $testGroups, $soundnessCases, $tools, false),
            'styleMatrix' => $styleCases === []
                ? ''
                : $this->renderMatrix($resultsRoot, $testGroups, $styleCases, $tools, true),
            'analyzers' => $this->render('analyzers.phtml', ['analyzers' => $this->analyzers]),
            'languageServers' => $this->render('language-servers.phtml', ['rows' => $this->languageServerRows()]),
        ]);

        return $this->renderPage('PHP Typing Conformance Results', $body, false);
    }

    /**
     * The PHP language servers, oldest first by initial release.
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
     * `note` keys attach a hover explanation to the preceding cell and
     * are used only where the short value would otherwise overstate the
     * claim -- "Own engine + adapter" needs to say which half is which,
     * "Not stated" needs to say what *is* known instead. Unused ones are
     * filled in as '' below, so the template never has to ask.
     *
     * @return list<array<string, string>>
     */
    private function languageServerRows(): array
    {
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
                'latestVersion' => '1.18.5',
                'latestDate' => '2026-06-21',
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
                'latestVersion' => '2026.07.22.0',
                'latestDate' => '2026-07-22',
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
                'latestVersion' => '6.16.1',
                'latestDate' => '2026-03-19',
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
                'latestVersion' => '1.0.19075',
                'latestDate' => '2026-07-16',
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
                'latestVersion' => '0.9.0',
                'latestDate' => '2026-07-19',
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
                'latestVersion' => '0.20.0',
                'latestDate' => '2026-07-19',
                'ast' => 'own (php-rs-parser)',
                'astNote' => 'Dedicated php-rs-parser and php-ast crates, published separately by the same author.',
                'announceUrl' => '',
                'announceLabel' => '',
            ],
        ];

        $optional = [
            'diagnosticsNote' => '',
            'analyzersNote' => '',
            'bundledNote' => '',
            'languageNote' => '',
            'licenseNote' => '',
            'initialNote' => '',
            'astNote' => '',
        ];

        return array_map(
            static fn (array $row): array => $row + $optional,
            $rows,
        );
    }

    /**
     * Render one results matrix for a subset of cases.
     *
     * @param array<string, TestGroup> $testGroups
     * @param list<TestCase> $cases
     * @param list<string> $tools
     */
    private function renderMatrix(
        string $resultsRoot,
        array $testGroups,
        array $cases,
        array $tools,
        bool $style,
    ): string {
        $toolColumns = [];
        foreach ($tools as $tool) {
            $toolColumns[] = [
                'name' => $tool,
                'versionHtml' => $this->versionCell($resultsRoot, $tool),
            ];
        }

        $groups = [];

        foreach ($testGroups as $groupKey => $group) {
            $groupCases = array_values(array_filter(
                $cases,
                static fn (TestCase $testCase): bool => $testCase->groupKey === $groupKey,
            ));

            if ($groupCases === []) {
                continue;
            }

            $rows = [];

            foreach ($groupCases as $testCase) {
                [$title] = $this->docblock($testCase);

                $cells = [];

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

                    $cells[] = [
                        'class' => $class,
                        'html' => $cell,
                        'notes' => trim((string) ($result['notes'] ?? '')),
                    ];
                }

                $rows[] = [
                    'id' => $testCase->name,
                    'href' => self::DETAILS_DIR . '/' . rawurlencode($testCase->name) . '.html',
                    'titleHtml' => h_inline($title),
                    'cells' => $cells,
                ];
            }

            $groups[] = ['name' => $group->name, 'rows' => $rows];
        }

        return $this->render('matrix.phtml', [
            'tools' => $toolColumns,
            'groups' => $groups,
        ]);
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

        $rows = [];

        foreach ($tools as $tool) {
            $result = $this->loadResult($resultsRoot, $tool, $testCase->name);
            [$display, $class] = $this->statusOf($result);
            $status = htmlspecialchars($display) . $this->levelSuffix($result) . $this->falsePositiveSuffix($result);

            $output = trim((string) ($result['output'] ?? ''));

            // The phpstan row also carries strict-rules-only diagnostics; the
            // psalm row carries pzoom's (Psalm port) diagnostics where they differ.
            $extra = null;
            if ($tool === 'phpstan') {
                [, , $suffix] = $this->phpstanMerged($resultsRoot, $testCase->name, $result, false);
                $status = htmlspecialchars($display) . ($output !== '' ? $this->levelSuffix($result) : $suffix);
                $strictOutput = trim((string) ($this->loadResult($resultsRoot, 'phpstan-strict', $testCase->name)['output'] ?? ''));
                if ($strictOutput !== '' && $strictOutput !== $output) {
                    $extra = [
                        'summary' => 'With strict-rules',
                        'output' => $strictOutput,
                        'emptyMessage' => '',
                    ];
                }
            } elseif ($tool === 'psalm') {
                [, , $suffix] = $this->psalmMerged($resultsRoot, $testCase->name, $result, false);
                $status = htmlspecialchars($display) . $suffix;
                if ($suffix !== '') {
                    $extra = [
                        'summary' => 'pzoom (Psalm port)',
                        'output' => trim((string) ($this->loadResult($resultsRoot, 'pzoom', $testCase->name)['output'] ?? '')),
                        'emptyMessage' => 'No diagnostics from pzoom.',
                    ];
                }
            }

            $rows[] = [
                'tool' => $tool,
                'versionHtml' => $this->versionCell($resultsRoot, $tool),
                'statusClass' => $class,
                'statusHtml' => $status,
                'output' => $output,
                'extra' => $extra,
                'facets' => $this->typeHandlingFacets($result),
                'errorsDiff' => trim((string) ($result['errors_diff'] ?? '')),
                'notes' => trim((string) ($result['notes'] ?? '')),
            ];
        }

        $supports = [];
        foreach ($testCase->supportPaths as $supportPath) {
            $supports[] = [
                'name' => basename($supportPath),
                'code' => $this->readSource($supportPath),
            ];
        }

        $body = $this->render('detail.phtml', [
            'indexHref' => '../' . self::INDEX_FILE,
            'titleHtml' => h_inline($title),
            'group' => $group === null
                ? null
                : ['name' => $group->name, 'category' => $group->sourceCategory],
            'file' => $testCase->fileName,
            'isStyle' => $this->testKind($testCase) === 'style',
            'description' => $description,
            'rows' => $rows,
            'source' => $this->readSource($testCase->path),
            'supports' => $supports,
        ]);

        return $this->renderPage($title . ' — Conformance', $body, true);
    }

    /**
     * Wrap a rendered body in the shared page skeleton.
     */
    private function renderPage(string $title, string $body, bool $isDetail): string
    {
        return $this->render('page.phtml', [
            'title' => $title,
            'bodyClass' => $isDetail ? 'detail' : 'index',
            // Detail pages sit one directory deeper than the index.
            'stylesheet' => ($isDetail ? '../' : '') . self::STYLESHEET_FILE,
            'body' => $body,
            'script' => $isDetail ? $this->render('highlight.phtml') : '',
        ]);
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function render(string $template, array $vars = []): string
    {
        return $this->renderer->render($template, $vars);
    }

    /**
     * Copy the stylesheet next to the generated pages.
     *
     * The report is a directory of ~100 detail pages plus an index; a linked
     * stylesheet keeps one copy of it instead of inlining the same block into
     * every page, and keeps the CSS editable as CSS. It sits with the
     * templates, so the renderer is asked where that is rather than this class
     * keeping a second path to the same directory.
     */
    private function copyStylesheet(string $resultsRoot): void
    {
        $destination = $resultsRoot . DIRECTORY_SEPARATOR . self::STYLESHEET_FILE;

        if (!copy($this->renderer->path(self::STYLESHEET_FILE), $destination)) {
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
     * The two facets behind a type-handling verdict, for the detail page to
     * spell out line by line: null for a test that is not type-handling.
     *
     * @param array<string, mixed> $result
     * @return array{recognition: string, enforced: string, incidental: bool, falsePositives: string}|null
     */
    private function typeHandlingFacets(array $result): ?array
    {
        if (!isset($result['recognition'])) {
            return null;
        }

        $unrecognized = $result['unrecognized_lines'] ?? [];
        $falsePositives = $result['false_positive_lines'] ?? [];
        $unresolved = is_array($unrecognized) && $unrecognized !== [];

        return [
            'recognition' => $unresolved
                ? sprintf('spelling not resolved — reported on declaration line(s) %s', implode(', ', $unrecognized))
                : 'spelling resolved',
            'enforced' => (string) ($result['enforced_lines'] ?? ''),
            'incidental' => $unresolved,
            'falsePositives' => is_array($falsePositives) && $falsePositives !== []
                ? sprintf('line(s) %s', implode(', ', $falsePositives))
                : '',
        ];
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
