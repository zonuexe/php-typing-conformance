<?php

declare(strict_types=1);

namespace Conformance\Reporting;

use Conformance\Discovery\TestCase;
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
            fn (TestCase $testCase): bool => $this->testKind($testCase) !== 'style',
        ));
        $styleCases = array_values(array_filter(
            $testCases,
            fn (TestCase $testCase): bool => $this->testKind($testCase) === 'style',
        ));

        $updatedAt = $this->resultsUpdate->recorded();

        $body = $this->render('index.phtml', [
            'updatedAt' => $updatedAt,
            'legend' => $this->render('legend.phtml'),
            'soundnessMatrix' => $this->renderMatrix($resultsRoot, $testGroups, $soundnessCases, $tools, false),
            'styleMatrix' => $styleCases === []
                ? ''
                : $this->renderMatrix($resultsRoot, $testGroups, $styleCases, $tools, true),
            'analyzers' => $this->render('analyzers.phtml', ['analyzers' => $this->analyzers->all()]),
            'languageServers' => $this->render('language-servers.phtml', ['servers' => $this->languageServers->all()]),
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

            foreach ($this->byTitle($groupCases) as [$testCase, $title]) {
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
    public function renderDetail(
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
}
