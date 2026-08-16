<?php

declare(strict_types=1);

namespace Conformance\Reporting;

use Conformance\Discovery\TestCase;
use Conformance\Discovery\TestCaseDiscovery;
use Conformance\Metadata\AnalyzerCatalog;
use Conformance\Metadata\LanguageServerCatalog;
use Conformance\Metadata\ReleaseTable;
use Conformance\Result\ResultsUpdate;
use Conformance\TestGroup\TestGroupLoader;

/**
 * The report, wired to the repository it is built from.
 *
 * Everything the report needs -- the test groups, the discovered cases, the
 * analyzer and language server metadata, the results directory -- is assembled
 * here once, so the three things that ask for a report (the static build, the
 * runner, the local server) do not each repeat the wiring.
 *
 * Pages are rendered, not read: write() puts them on disk for publishing, and
 * index()/detail() hand back the same HTML as a string for serving. Neither
 * depends on the other having run.
 */
final class Report
{
    /**
     * The columns the matrix shows.
     *
     * phpstan-strict and pzoom are run for their data but are not columns of
     * their own: the report merges the first into phpstan and the second into
     * psalm.
     *
     * qodana and phpactor sit next to intelephense because the three answer the
     * same way: the analysis an editor performs while you type, rather than a
     * checker a pipeline invokes.
     *
     * @var list<string>
     */
    public const DISPLAY_TOOLS = ['phan', 'phpstan', 'psalm', 'mago', 'mir', 'phpantom', 'intelephense', 'phpy', 'qodana', 'phpactor', 'noverify', 'steins'];

    /**
     * @param array<string, \Conformance\TestGroup\TestGroup> $testGroups
     * @param list<TestCase> $testCases
     * @param list<string> $tools
     */
    private function __construct(
        private readonly SummaryReport $summary,
        private readonly string $resultsRoot,
        private readonly array $testGroups,
        private readonly array $testCases,
        private readonly array $tools,
    ) {
    }

    /**
     * @param string $rootDir the conformance/ directory
     * @param list<string>|null $tools display columns, defaulting to DISPLAY_TOOLS
     */
    public static function fromRootDir(string $rootDir, ?array $tools = null): self
    {
        $releases = ReleaseTable::fromTomlFile($rootDir . '/data/releases.toml');
        $testGroups = (new TestGroupLoader())->load($rootDir . '/src/test-groups.toml');

        return new self(
            new SummaryReport(
                new TemplateRenderer($rootDir . '/templates'),
                AnalyzerCatalog::build($releases, self::versionBanners($rootDir)),
                LanguageServerCatalog::build($releases),
                new ResultsUpdate($rootDir . '/results', $rootDir . '/tests'),
            ),
            $rootDir . '/results',
            $testGroups,
            (new TestCaseDiscovery())->discover($rootDir . '/tests', $testGroups),
            $tools ?? self::DISPLAY_TOOLS,
        );
    }

    /**
     * The raw version banners recorded per results/<tool>/version.toml, for
     * the evaluated-release column: the version the suite actually ran.
     *
     * @return array<string, string> keyed by tool name
     */
    private static function versionBanners(string $rootDir): array
    {
        $banners = [];

        foreach (glob($rootDir . '/results/*/version.toml') ?: [] as $path) {
            $tool = basename(dirname($path));
            $contents = file_get_contents($path);

            if ($contents !== false && preg_match('/^version\s*=\s*["\'](.+)["\']/m', $contents, $m) === 1) {
                $banners[$tool] = $m[1];
            }
        }

        return $banners;
    }

    /**
     * Write the whole report to the results directory, for publishing.
     *
     * @return string path of the index page
     */
    public function write(): string
    {
        return $this->summary->generate($this->resultsRoot, $this->testGroups, $this->testCases, $this->tools);
    }

    public function index(): string
    {
        return $this->summary->renderIndex($this->resultsRoot, $this->testGroups, $this->testCases, $this->tools);
    }

    /**
     * One test's page, or null if no test goes by that name.
     */
    public function detail(string $testName): ?string
    {
        foreach ($this->testCases as $testCase) {
            if ($testCase->name === $testName) {
                return $this->summary->renderDetail(
                    $this->resultsRoot,
                    $testCase,
                    $this->testGroups[$testCase->groupKey] ?? null,
                    $this->tools,
                );
            }
        }

        return null;
    }

    public function stylesheet(): string
    {
        return $this->summary->stylesheet();
    }

    public function ogpImage(): string
    {
        return $this->summary->ogpImage();
    }
}
