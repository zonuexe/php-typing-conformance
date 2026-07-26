<?php

declare(strict_types=1);

namespace Conformance\Reporting;

use Conformance\Discovery\TestCase;
use Conformance\Discovery\TestCaseDiscovery;
use Conformance\Metadata\AnalyzerCatalog;
use Conformance\Metadata\LanguageServerCatalog;
use Conformance\Metadata\ReleaseTable;
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
     * @var list<string>
     */
    public const DISPLAY_TOOLS = ['phan', 'phpstan', 'psalm', 'mago', 'mir', 'noverify', 'intelephense', 'phpy', 'steins'];

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
                AnalyzerCatalog::build($releases),
                LanguageServerCatalog::build($releases),
            ),
            $rootDir . '/results',
            $testGroups,
            (new TestCaseDiscovery())->discover($rootDir . '/tests', $testGroups),
            $tools ?? self::DISPLAY_TOOLS,
        );
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
}
