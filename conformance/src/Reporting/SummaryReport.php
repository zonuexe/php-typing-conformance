<?php

declare(strict_types=1);

namespace Conformance\Reporting;

use Conformance\Discovery\TestCase;
use Conformance\Lsp\ProbeGrading;
use Conformance\Metadata\AnalyzerCatalog;
use Conformance\Metadata\LanguageServerCatalog;
use Conformance\Result\ResultsUpdate;
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
    private const INDEX_FILE = 'index.html';
    private const STYLESHEET_FILE = 'report.css';
    private const OGP_IMAGE_FILE = 'ogp.png';

    /** What loadVersion() reports when a tool has not recorded one. */
    private const UNKNOWN_VERSION = 'unknown';

    public function __construct(
        private readonly TemplateRenderer $renderer,
        private readonly AnalyzerCatalog $analyzers,
        private readonly LanguageServerCatalog $languageServers,
        private readonly ResultsUpdate $resultsUpdate,
    ) {
    }

    /**
     * Write the whole report out as static files.
     *
     * The same pages renderIndex() and renderDetail() return; this is the form
     * the site is published from.
     *
     * @param array<string, TestGroup> $testGroups
     * @param list<TestCase> $testCases
     * @param list<string> $tools
     * @return string path of the index page
     */
    public function generate(
        string $resultsRoot,
        array $testGroups,
        array $testCases,
        array $tools,
    ): string {
        $detailsDir = $resultsRoot . '/' . self::DETAILS_DIR;
        $this->prepareDetailsDir($detailsDir);
        $this->writeStylesheet($resultsRoot);
        $this->writeOgpImage($resultsRoot);

        // Detail pages first so the index can link to known files.
        foreach ($testCases as $testCase) {
            $group = $testGroups[$testCase->groupKey] ?? null;
            $detailHtml = $this->renderDetail($resultsRoot, $testCase, $group, $tools);
            $detailPath = "{$detailsDir}/{$testCase->name}.html";

            if (file_put_contents($detailPath, $detailHtml) === false) {
                throw new RuntimeException(sprintf('Failed to write detail page: %s', $detailPath));
            }
        }

        $indexPath = $resultsRoot . '/' . self::INDEX_FILE;

        if (file_put_contents($indexPath, $this->renderIndex($resultsRoot, $testGroups, $testCases, $tools)) === false) {
            throw new RuntimeException(sprintf('Failed to write summary report: %s', $indexPath));
        }

        return $indexPath;
    }

    /**
     * @param array<string, TestGroup> $testGroups
     * @param list<TestCase> $testCases
     * @param list<string> $tools
     */
    public function renderIndex(
        string $resultsRoot,
        array $testGroups,
        array $testCases,
        array $tools,
    ): string {
        $soundnessCases = array_values(array_filter(
            $testCases,
            fn (TestCase $testCase): bool => !in_array($this->testKind($testCase), ['style', 'debug'], true),
        ));
        $styleCases = array_values(array_filter(
            $testCases,
            fn (TestCase $testCase): bool => $this->testKind($testCase) === 'style',
        ));
        $debugCases = array_values(array_filter(
            $testCases,
            fn (TestCase $testCase): bool => $this->testKind($testCase) === 'debug',
        ));

        $updatedAt = $this->resultsUpdate->recorded();

        $body = $this->render('index.phtml', [
            'updatedAt' => $updatedAt,
            'legend' => $this->render('legend.phtml'),
            'soundnessMatrix' => $this->renderMatrix($resultsRoot, $testGroups, $soundnessCases, $tools, false),
            'styleMatrix' => $styleCases === []
                ? ''
                : $this->renderMatrix($resultsRoot, $testGroups, $styleCases, $tools, true),
            'debugMatrix' => $debugCases === []
                ? ''
                : $this->renderMatrix($resultsRoot, $testGroups, $debugCases, $tools, false),
            'analyzers' => $this->render('analyzers.phtml', ['analyzers' => $this->analyzers->all()]),
            'languageServers' => $this->render('language-servers.phtml', ['servers' => $this->languageServers->all()]),
            'languageServerCapabilities' => $this->renderLanguageServerCapabilities($resultsRoot),
        ]);

        return $this->renderPage('PHP Typing Conformance Results', $body, false);
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
            // Configurations (phpstan-strict, psalm-next) are not columns of
            // their own; their data rides in the base column's cell and on
            // the detail pages.
            if ($this->analyzers->isConfiguration($tool)) {
                continue;
            }

            $toolColumns[] = [
                'name' => $tool,
                // The header shows only the column's own version; configuration
                // lines (psalm-next) stay on the detail pages.
                'versionHtml' => $this->versionCell($resultsRoot, $tool, false),
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

            // Advanced PHPDoc mixes type spellings (int-range, class-string, …)
            // with annotation tags (@psalm-assert, @mixin, …). One header for both
            // makes the matrix hard to scan, so the display splits them.
            if ($groupKey === 'phpdoc_advanced') {
                $typeCases = [];
                $tagCases = [];
                foreach ($groupCases as $testCase) {
                    if ($this->isPhpdocTagCase($testCase)) {
                        $tagCases[] = $testCase;
                    } else {
                        $typeCases[] = $testCase;
                    }
                }

                if ($typeCases !== []) {
                    $groups[] = [
                        'name' => $group->name,
                        'rows' => $this->matrixRows($resultsRoot, $typeCases, $tools, $style),
                    ];
                }

                if ($tagCases !== []) {
                    $groups[] = [
                        'name' => 'PHPDoc tags',
                        'rows' => $this->matrixRows($resultsRoot, $tagCases, $tools, $style),
                    ];
                }

                continue;
            }

            $groups[] = [
                'name' => $group->name,
                'rows' => $this->matrixRows($resultsRoot, $groupCases, $tools, $style),
            ];
        }

        return $this->render('matrix.phtml', [
            'tools' => $toolColumns,
            'groups' => $groups,
        ]);
    }

    /**
     * Build the matrix body rows for a set of test cases.
     *
     * @param list<TestCase> $cases
     * @param list<string> $tools
     * @return list<array{id: string, href: string, titleHtml: string, cells: list<array{class: string, html: string, notes: string}>}>
     */
    private function matrixRows(
        string $resultsRoot,
        array $cases,
        array $tools,
        bool $style,
    ): array {
        $rows = [];

        foreach ($this->byTitle($cases) as [$testCase, $title]) {
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

        return $rows;
    }

    /**
     * Whether a phpdoc_advanced case is about an annotation tag rather than a
     * type spelling.
     *
     * Tag cases are those whose `// T` marker names a docblock tag (`@…`), or
     * whose filename is a vendor-prefixed tag probe without a `// T` marker
     * (e.g. `@phan-param` under `vendor_prefixed_param_*`).
     */
    private function isPhpdocTagCase(TestCase $testCase): bool
    {
        $source = $this->readSource($testCase->path);
        if (preg_match('/\/\/\s*T:\s*@\S+/', $source) === 1) {
            return true;
        }

        return str_contains($testCase->name, 'vendor_prefixed');
    }

    /**
     * @param list<string> $tools
     */
    public function renderDetail(
        string $resultsRoot,
        TestCase $testCase,
        ?TestGroup $group,
        array $tools,
    ): string {
        // The detail pages show the configurations too — psalm-next gets a
        // full row here even though the index matrix folds it into psalm.
        foreach ($tools as $tool) {
            foreach ($this->analyzers->configurationsOf($tool) as $configuration) {
                $tools[] = $configuration['tool'];
            }
        }
        $tools = array_values(array_unique($tools));

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
     * The stylesheet every page links to.
     *
     * The report is ~100 detail pages plus an index; one linked stylesheet
     * beats inlining the same block into each of them, and keeps the CSS
     * editable as CSS. It sits with the templates, so the renderer is asked
     * where that is rather than this class keeping a second path to the same
     * directory.
     */
    public function stylesheet(): string
    {
        $path = $this->renderer->path(self::STYLESHEET_FILE);
        $css = file_get_contents($path);

        if ($css === false) {
            throw new RuntimeException(sprintf('Failed to read stylesheet: %s', $path));
        }

        return $css;
    }

    /**
     * Put it next to the generated pages, for the static build.
     */
    private function writeStylesheet(string $resultsRoot): void
    {
        $destination = $resultsRoot . '/' . self::STYLESHEET_FILE;

        if (file_put_contents($destination, $this->stylesheet()) === false) {
            throw new RuntimeException(sprintf('Failed to write stylesheet: %s', $destination));
        }
    }

    /**
     * The Open Graph / Twitter Card image every page references.
     *
     * Authored next to the stylesheet under templates/; the static build
     * copies it next to the generated pages so the published site can serve
     * it at a stable absolute URL.
     */
    public function ogpImage(): string
    {
        $path = $this->renderer->path(self::OGP_IMAGE_FILE);
        $image = file_get_contents($path);

        if ($image === false) {
            throw new RuntimeException(sprintf('Failed to read OGP image: %s', $path));
        }

        return $image;
    }

    /**
     * Put the OGP image next to the generated pages, for the static build.
     */
    private function writeOgpImage(string $resultsRoot): void
    {
        $destination = $resultsRoot . '/' . self::OGP_IMAGE_FILE;

        if (file_put_contents($destination, $this->ogpImage()) === false) {
            throw new RuntimeException(sprintf('Failed to write OGP image: %s', $destination));
        }
    }

    /**
     * The cases of one group, paired with their titles and ordered by them.
     *
     * Rows read as an alphabetical index of what the group covers -- the type
     * spellings and PHPDoc tags themselves, not the file names they happen to
     * live under. Sorting by file name put `associative-array` ahead of
     * `array-key` (one file says `fallback_`, the other does not) and buried
     * `int-range<0, 255>` far below `int<0, 255>`, which is unreadable in a
     * list this long.
     *
     * @param list<TestCase> $cases
     * @return list<array{0: TestCase, 1: string}>
     */
    private function byTitle(array $cases): array
    {
        $titled = [];
        foreach ($cases as $testCase) {
            [$title] = $this->docblock($testCase);
            $titled[] = [$testCase, $title];
        }

        usort(
            $titled,
            static function (array $left, array $right): int {
                $order = strnatcasecmp(self::sortKey($left[1]), self::sortKey($right[1]));

                return $order !== 0 ? $order : ($left[0]->fileName <=> $right[0]->fileName);
            },
        );

        return $titled;
    }

    /**
     * A title reduced to what it should alphabetize under.
     *
     * Titles wear decoration that would otherwise drive the order: the markup
     * backticks around a spelling, and leading punctuation such as the `@` of a
     * tag or the `!==` of a narrowing test. Dropping it files `@phpstan-param`
     * under P and `` `array-key` `` under A, where a reader looks for them.
     */
    private static function sortKey(string $title): string
    {
        $stripped = ltrim(str_replace('`', '', $title));
        $stripped = preg_replace('/^[^\p{L}\p{N}]+/u', '', $stripped) ?? $stripped;

        return $stripped === '' ? $title : $stripped;
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

        // Many advanced/debug cases open with this stock phrase; the matrix and
        // detail headings are clearer as just the tag or helper under test.
        $stripped = preg_replace('/^Cross-tool handling of\s+/i', '', $titleText);
        if (is_string($stripped) && $stripped !== $titleText) {
            $titleText = trim($stripped);
            if ($titleText !== '' && preg_match('/^[\p{Ll}]/u', $titleText) === 1) {
                $titleText = mb_strtoupper(mb_substr($titleText, 0, 1)) . mb_substr($titleText, 1);
            }
        }

        // Headings read better without the sentence-ending period (but keep an
        // ellipsis intact).
        if (str_ends_with($titleText, '.') && !str_ends_with($titleText, '..')) {
            $titleText = substr($titleText, 0, -1);
        }

        if ($titleText === '') {
            $titleText = $this->humanize($testCase->name);
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
            'Not measured' => 'not-measured',
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
        // A hand-run analyzer that has not seen this test yet has no
        // recognition or enforcement to report, and saying "Unrecognized"
        // would blame it for a measurement nobody took.
        if ((string) ($result['conformance_automated'] ?? '') === 'Not measured') {
            return ['Not measured', 'not-measured'];
        }

        if ((string) $result['recognition'] === 'unrecognized') {
            return ['Unrecognized', 'not-supported'];
        }

        $enforcement = (string) ($result['enforcement'] ?? '');
        $overRejected = $result['over_rejected_lines'] ?? [];
        $rejectedValid = is_array($overRejected) && $overRejected !== [];

        // Hits on the violating lines are not enforcement when the analyzer
        // also rejects values the type admits (class-name fallback, sealed
        // where the test asked for unsealed, over-strict purity, …).
        if ($rejectedValid && ($enforcement === 'enforced' || $enforcement === 'partial' || $enforcement === 'none')) {
            $ratio = (string) ($result['enforced_lines'] ?? '');

            return [
                $ratio !== '' ? sprintf('Incidental (%s)', $ratio) : 'Incidental',
                'incidental',
            ];
        }

        if ($enforcement === 'enforced') {
            return ['Enforced', 'pass'];
        }

        if ($enforcement === 'partial') {
            return [
                sprintf('Partly enforced (%s)', (string) ($result['enforced_lines'] ?? '')),
                'partial',
            ];
        }

        // No probes at all: the spelling resolved, and nothing was ever asked of
        // enforcement. Saying "Not enforced" here would read as a miss.
        if ($enforcement === 'no-probes') {
            return ['Recognized (no probes)', 'no-probes'];
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
     * @return array{recognition: string, enforced: string, incidental: bool, incidentalReason: string, noProbes: bool, falsePositives: string}|null
     */
    private function typeHandlingFacets(array $result): ?array
    {
        if (!isset($result['recognition'])) {
            return null;
        }

        $unrecognized = $result['unrecognized_lines'] ?? [];
        $falsePositives = $result['false_positive_lines'] ?? [];
        $overRejected = $result['over_rejected_lines'] ?? [];
        $unresolved = is_array($unrecognized) && $unrecognized !== [];
        $rejectedValid = is_array($overRejected) && $overRejected !== [];

        $incidentalReason = '';
        if ($unresolved) {
            $incidentalReason = 'incidental, since the spelling was not resolved';
        } elseif ($rejectedValid) {
            $incidentalReason = sprintf(
                'incidental, since values the type admits were also rejected on line(s) %s',
                implode(', ', $overRejected),
            );
        }

        return [
            'recognition' => $unresolved
                ? sprintf('spelling not resolved — reported on declaration line(s) %s', implode(', ', $unrecognized))
                : 'spelling resolved',
            'enforced' => (string) ($result['enforced_lines'] ?? ''),
            'incidental' => $unresolved || $rejectedValid,
            'incidentalReason' => $incidentalReason,
            'noProbes' => (string) ($result['enforcement'] ?? '') === 'no-probes',
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
     * Omitted when the spelling was not resolved. Recognition is
     * level-independent, and the stored level on those rows is usually the
     * mixed-fallout diagnostic, not the unresolvable-type one.
     *
     * @param array<string, mixed> $result
     */
    private function levelSuffix(array $result): string
    {
        if (($result['recognition'] ?? null) === 'unrecognized') {
            return '';
        }

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

    private function versionCell(string $resultsRoot, string $tool, bool $withConfigurations = true): string
    {
        $analyzer = $this->analyzers->find($tool);
        $fullVersion = $this->loadVersion($resultsRoot, $tool);
        $shortVersion = $analyzer?->shortVersion($fullVersion) ?? trim($fullVersion);
        $releaseUrl = $shortVersion === self::UNKNOWN_VERSION
            ? null
            : $analyzer?->releaseUrl($shortVersion);
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

        $card = sprintf(
            '<span class="hover-card">%s<span class="hover-card__popup">%s</span></span>',
            $versionHtml,
            $popupHtml,
        );

        // A configuration that runs a different binary — psalm-next under
        // psalm — is named on its own line in the base column's version cell,
        // so the folded line stays visible where the column is. A
        // configuration that reuses the same binary (phpstan-strict) has the
        // same version and adds nothing. The matrix header does not show the
        // line; the detail pages do.
        if ($withConfigurations) {
            foreach ($this->analyzers->configurationsOf($tool) as $configuration) {
                $configShort = $configuration['release']->version;
                if ($configShort === $shortVersion) {
                    continue;
                }

                $configUrl = $analyzer?->releaseUrl($configShort);
                $configHtml = htmlspecialchars($configShort);
                if ($configUrl !== null) {
                    $configHtml = sprintf(
                        '<a href="%s" target="_blank" rel="noopener">%s</a>',
                        htmlspecialchars($configUrl),
                        $configHtml,
                    );
                }

                $card .= sprintf('<br>next: %s', $configHtml);
            }
        }

        return $card;
    }

    private function prepareDetailsDir(string $detailsDir): void
    {
        if (!is_dir($detailsDir) && !mkdir($detailsDir, 0o755, true) && !is_dir($detailsDir)) {
            throw new RuntimeException(sprintf('Failed to create details directory: %s', $detailsDir));
        }

        // Drop stale detail pages so removed tests do not linger.
        foreach (glob($detailsDir . '/*.html') ?: [] as $stale) {
            @unlink($stale);
        }
    }

    private function loadVersion(string $resultsRoot, string $tool): string
    {
        $path = "{$resultsRoot}/{$tool}/version.toml";
        if (!is_file($path)) {
            return self::UNKNOWN_VERSION;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return self::UNKNOWN_VERSION;
        }

        $data = Toml::parseToArray($contents);

        return (string) ($data['version'] ?? self::UNKNOWN_VERSION);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadResult(string $resultsRoot, string $tool, string $testName): array
    {
        $path = "{$resultsRoot}/{$tool}/{$testName}.toml";
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }

        return Toml::parseToArray($contents);
    }

    /**
     * Columns of the measured capability matrix, in claims-table order for
     * the tools both tables carry. run-lsp-probes.php decides which servers
     * get measured; this list only decides who stands where.
     */
    private const LSP_TOOL_ORDER = ['intelephense', 'phpactor', 'psalm', 'devsense-php-ls', 'phpantom', 'php-lsp', 'phan', 'laravel-lsp'];

    /** Row labels of the capability matrix, in ProbeGrading::COLUMNS order. */
    private const LSP_CAPABILITY_LABELS = [
        'push-diagnostics' => 'Diagnostics (push)',
        'pull-diagnostics' => 'Diagnostics (pull)',
        'hover' => 'Hover',
        'completion' => 'Completion',
        'signature-help' => 'Signature help',
        'definition' => 'Go to definition',
        'declaration' => 'Go to declaration',
        'type-definition' => 'Go to type definition',
        'implementation' => 'Go to implementation',
        'references' => 'Find references',
        'document-highlight' => 'Document highlight',
        'document-symbol' => 'Document symbols',
        'workspace-symbol' => 'Workspace symbols',
        'code-action' => 'Code actions',
        'code-lens' => 'Code lens',
        'rename' => 'Rename',
        'formatting' => 'Formatting',
        'range-formatting' => 'Range formatting',
        'folding-range' => 'Folding ranges',
        'selection-range' => 'Selection ranges',
        'semantic-tokens' => 'Semantic tokens',
        'inlay-hint' => 'Inlay hints',
        'call-hierarchy' => 'Call hierarchy',
        'type-hierarchy' => 'Type hierarchy',
    ];

    /**
     * The measured half of the language-server story: what each launchable
     * server's initialize handshake advertised and how it answered the
     * probes, from results/lsp/<tool>.toml. Renders to '' until the first
     * probe run has been committed, so the claims table can ship alone.
     */
    private function renderLanguageServerCapabilities(string $resultsRoot): string
    {
        $results = [];
        foreach (self::LSP_TOOL_ORDER as $tool) {
            $path = "{$resultsRoot}/lsp/{$tool}.toml";
            $contents = is_file($path) ? file_get_contents($path) : false;
            if ($contents === false) {
                continue;
            }
            $results[$tool] = Toml::parseToArray($contents);
        }

        if ($results === []) {
            return '';
        }

        $tools = [];
        foreach ($results as $tool => $data) {
            // "Psalm 6.16.1@f1f5de..." carries the commit for provenance; the
            // column header wants the human half, the title keeps it whole.
            $version = (string) ($data['version'] ?? self::UNKNOWN_VERSION);
            $short = explode('@', $version, 2)[0];
            $tools[] = ['name' => $tool, 'version' => $short, 'versionFull' => $version];
        }

        $capabilityRows = [];
        foreach (array_keys(ProbeGrading::COLUMNS) as $column) {
            $cells = [];
            foreach ($results as $data) {
                $cells[] = $this->lspCapabilityCell($column, $data['capabilities'][$column] ?? []);
            }
            $capabilityRows[] = [
                'label' => self::LSP_CAPABILITY_LABELS[$column] ?? $column,
                'cells' => $cells,
            ];
        }

        $hoverRows = [];
        $caseIds = array_keys(((array) reset($results))['hover'] ?? []);
        foreach ($caseIds as $caseId) {
            $first = reset($results)['hover'][$caseId];
            $cells = [];
            foreach ($results as $data) {
                $cells[] = $this->lspHoverCell($data['hover'][$caseId] ?? []);
            }
            $hoverRows[] = [
                'feature' => (string) ($first['feature'] ?? $caseId),
                'expected' => (string) ($first['expected'] ?? ''),
                'cells' => $cells,
            ];
        }

        $navCorpus = '';
        $navFailures = [];
        $navDefRows = [];
        $navRefRows = [];
        $withNavigation = array_filter($results, static fn (array $data): bool => isset($data['navigation']));
        if ($withNavigation !== []) {
            $first = (array) reset($withNavigation);
            $navCorpus = (string) ($first['navigation_corpus'] ?? '');
            foreach ($results as $tool => $data) {
                if (isset($data['navigation_failure'])) {
                    $navFailures[$tool] = (string) $data['navigation_failure'];
                }
            }
            foreach (array_keys($first['navigation']) as $symbolId) {
                $symbol = $first['navigation'][$symbolId];
                $defCells = [];
                $refCells = [];
                foreach ($results as $data) {
                    $row = $data['navigation'][$symbolId] ?? [];
                    $defCells[] = $this->lspNavigationDefinitionCell($row);
                    $refCells[] = $this->lspNavigationReferencesCell($row);
                }
                $label = ['kind' => (string) ($symbol['kind'] ?? $symbolId), 'name' => (string) ($symbol['name'] ?? '')];
                // A symbol may be measured for references alone; the constructor
                // is, because go-to-definition from a `new` expression has no
                // gradeable right answer. It gets no row in the definition table.
                if (($symbol['definition'] ?? '') !== 'n/a') {
                    $navDefRows[] = [...$label, 'cells' => $defCells];
                }
                $navRefRows[] = [...$label, 'cells' => $refCells];
            }
        }

        $frameworkRows = [];
        $frameworkIds = [];
        foreach ($results as $data) {
            foreach (array_keys($data['framework'] ?? []) as $id) {
                $frameworkIds[$id] = true;
            }
        }
        foreach (array_keys($frameworkIds) as $id) {
            $cells = [];
            $label = $id;
            foreach ($results as $data) {
                $row = $data['framework'][$id] ?? [];
                if ($label === $id && ($row['feature'] ?? '') !== '') {
                    $label = (string) $row['feature'];
                }
                $cells[] = $this->lspFrameworkCell($row);
            }
            $frameworkRows[] = ['label' => $label, 'cells' => $cells];
        }

        return $this->render('language-server-capabilities.phtml', [
            'tools' => $tools,
            'capabilityRows' => $capabilityRows,
            'hoverRows' => $hoverRows,
            'frameworkRows' => $frameworkRows,
            'navCorpus' => $navCorpus,
            'navFailures' => $navFailures,
            'navDefRows' => $navDefRows,
            'navRefRows' => $navRefRows,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{class: string, text: string, note: string}
     */
    private function lspFrameworkCell(array $row): array
    {
        if ($row === []) {
            return ['class' => 'not-supported', 'text' => '—', 'note' => 'This server was not asked this framework probe.'];
        }

        $shown = (string) ($row['shown'] ?? '');
        $expected = (string) ($row['expected'] ?? '');
        $note = $shown !== '' ? $shown : (string) ($row['note'] ?? '');
        if ($expected !== '' && $shown !== '' && str_contains($shown, $expected)) {
            return ['class' => 'pass', 'text' => 'Precise', 'note' => $note];
        }

        return match ((string) ($row['probe'] ?? '')) {
            'answered' => ['class' => 'pass', 'text' => 'Answered', 'note' => $note],
            'empty' => ['class' => 'fail', 'text' => 'No answer', 'note' => 'The request succeeded but the payload was empty.'],
            'timeout' => ['class' => 'fail', 'text' => 'Timed out', 'note' => 'No response within the probe timeout.'],
            'error' => ['class' => 'fail', 'text' => 'Error', 'note' => $note],
            'not-probed', 'skipped' => ['class' => 'not-supported', 'text' => '—', 'note' => 'Not sent.'],
            default => ['class' => 'unknown', 'text' => '?', 'note' => 'No measurement recorded.'],
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array{class: string, text: string, note: string}
     */
    private function lspNavigationDefinitionCell(array $row): array
    {
        return match ((string) ($row['definition'] ?? '')) {
            'correct' => ['class' => 'pass', 'text' => 'Correct', 'note' => 'A returned range in the right file covers the declaration line.'],
            'wrong-location' => ['class' => 'fail', 'text' => 'Wrong location', 'note' => 'Answered, but landed at ' . (string) ($row['definition_actual'] ?? 'an unexpected location') . '.'],
            'empty' => ['class' => 'fail', 'text' => 'No answer', 'note' => 'The request succeeded but returned no location.'],
            'timeout' => ['class' => 'fail', 'text' => 'Timed out', 'note' => 'No response within the probe timeout.'],
            'error' => ['class' => 'fail', 'text' => 'Error', 'note' => 'The request failed.'],
            'skipped' => ['class' => 'not-supported', 'text' => '—', 'note' => 'Go to definition is not advertised by this server.'],
            default => ['class' => 'unknown', 'text' => '?', 'note' => 'No measurement recorded.'],
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array{class: string, text: string, note: string}
     */
    private function lspNavigationReferencesCell(array $row): array
    {
        $found = (int) ($row['refs_found'] ?? 0);
        $expected = (int) ($row['refs_expected'] ?? 0);
        $extra = (int) ($row['refs_extra'] ?? 0);
        $score = "{$found}/{$expected}" . ($extra > 0 ? " +{$extra}" : '');
        $extraNote = $extra > 0 ? " {$extra} location(s) beyond the expected set were also returned." : '';
        /** @var list<string> $missing */
        $missing = $row['refs_missing'] ?? [];
        $missingNote = $missing === [] ? '' : ' Missing: ' . implode(', ', $missing) . '.';

        return match ((string) ($row['references'] ?? '')) {
            'all' => ['class' => 'pass', 'text' => $score, 'note' => 'Every expected reference was enumerated.' . $extraNote],
            'partial' => ['class' => 'partial', 'text' => $score, 'note' => 'Part of the expected reference set was enumerated.' . $missingNote . $extraNote],
            'none' => ['class' => 'fail', 'text' => $score, 'note' => 'No expected reference was enumerated.' . $extraNote],
            'timeout' => ['class' => 'fail', 'text' => 'Timed out', 'note' => 'No response within the probe timeout.'],
            'error' => ['class' => 'fail', 'text' => 'Error', 'note' => 'The request failed.'],
            'skipped' => ['class' => 'not-supported', 'text' => '—', 'note' => 'Find references is not advertised by this server.'],
            default => ['class' => 'unknown', 'text' => '?', 'note' => 'No measurement recorded.'],
        };
    }

    /**
     * One capability cell: the handshake's claim and the probe's answer,
     * folded into a word. The vocabulary deliberately never says "supported"
     * — every value states what was observed and nothing more.
     *
     * @param array<string, mixed> $row
     * @return array{class: string, text: string, note: string}
     */
    private function lspCapabilityCell(string $column, array $row): array
    {
        $probe = (string) ($row['probe'] ?? 'not-probed');

        if ($column === 'push-diagnostics') {
            // The push model has no capability flag to advertise, so this row
            // is behaviour alone: did the session ever carry diagnostics for
            // the fixture holding the deliberate type error?
            return $probe === 'answered'
                ? ['class' => 'pass', 'text' => 'Publishes', 'note' => 'The server published diagnostics for the fixture with the deliberate type error.']
                : ['class' => 'not-supported', 'text' => 'Silent', 'note' => 'The server never published a diagnostic for the fixture with the deliberate type error. The protocol has no capability flag for push diagnostics, so behaviour is the whole measurement.'];
        }

        if (($row['advertised'] ?? false) !== true) {
            return ['class' => 'not-supported', 'text' => '—', 'note' => 'Not advertised in the initialize handshake.'];
        }

        $via = ($row['via'] ?? '') === 'dynamic' ? ' (registered dynamically after initialize)' : '';

        return match ($probe) {
            'answered' => ['class' => 'pass', 'text' => 'Answered', 'note' => 'Advertised' . $via . ', and the probe request got a non-empty answer.'],
            'empty' => ['class' => 'partial', 'text' => 'Empty', 'note' => 'Advertised' . $via . ', but the probe request came back empty at a position where an answer was expected.'],
            'timeout' => ['class' => 'fail', 'text' => 'No answer', 'note' => 'Advertised' . $via . ', but the probe request got no response before the timeout.'],
            'gated' => ['class' => 'by-design', 'text' => 'Gated', 'note' => trim('Advertised, but the server refused the call by licence: ' . (string) ($row['note'] ?? ''))],
            'error' => ['class' => 'fail', 'text' => 'Error', 'note' => trim('Advertised' . $via . ', but the probe request failed: ' . (string) ($row['note'] ?? ''))],
            default => ['class' => 'reported', 'text' => 'Advertised', 'note' => 'Advertised in the handshake' . $via . '; no probe exercises this capability yet, so this cell records the claim alone.'],
        };
    }

    /**
     * One hover-conformance cell; the note carries what the server actually
     * showed, so a "Widened / other" verdict is always auditable in place.
     *
     * @param array<string, mixed> $row
     * @return array{class: string, text: string, note: string}
     */
    private function lspHoverCell(array $row): array
    {
        $shown = trim((string) ($row['shown'] ?? ''));
        $shownNote = $shown === '' ? '' : "The server showed:\n" . $shown;

        return match ((string) ($row['verdict'] ?? '')) {
            'precise' => ['class' => 'pass', 'text' => 'Precise', 'note' => $shownNote],
            'other' => ['class' => 'partial', 'text' => 'Widened / other', 'note' => $shownNote],
            'none' => ['class' => 'fail', 'text' => 'No type shown', 'note' => 'Hover answered nothing at this position.'],
            'not-advertised' => ['class' => 'not-supported', 'text' => '—', 'note' => 'Hover is not advertised by this server.'],
            default => ['class' => 'unknown', 'text' => '?', 'note' => 'No measurement recorded.'],
        };
    }
}
