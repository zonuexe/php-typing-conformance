<?php

declare(strict_types=1);

use Conformance\Discovery\TestCaseDiscovery;
use Conformance\Metadata\AnalyzerCatalog;
use Conformance\Metadata\ReleaseTable;
use Conformance\Reporting\SummaryReport;
use Conformance\Reporting\TemplateRenderer;
use Conformance\TestGroup\TestGroupLoader;

require_once __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);
$testGroupsFile = $rootDir . '/src/test-groups.toml';
$testsDir = $rootDir . '/tests';
$resultsDir = $rootDir . '/results';
$templatesDir = $rootDir . '/templates';
$analyzerReleasesFile = $rootDir . '/data/analyzer-releases.toml';

$loader = new TestGroupLoader();
$testGroups = $loader->load($testGroupsFile);
$discovery = new TestCaseDiscovery();
$testCases = $discovery->discover($testsDir, $testGroups);
$summaryReport = new SummaryReport(
    new TemplateRenderer($templatesDir),
    AnalyzerCatalog::build(ReleaseTable::fromTomlFile($analyzerReleasesFile)),
);
$summaryPath = $resultsDir . '/results.html';
// phpstan-strict is merged into the phpstan column by the report, so it is not
// listed as its own display column here.
$tools = ['phan', 'phpstan', 'psalm', 'mago', 'mir', 'noverify', 'intelephense', 'phpy', 'steins'];

$summaryReport->generate(
    resultsRoot: $resultsDir,
    outputPath: $summaryPath,
    testGroups: $testGroups,
    testCases: $testCases,
    tools: $tools,
);

printf("Generated summary report at %s\n", $summaryPath);
