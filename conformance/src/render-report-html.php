<?php

declare(strict_types=1);

use Conformance\Discovery\TestCaseDiscovery;
use Conformance\Reporting\SummaryReport;
use Conformance\TestGroup\TestGroupLoader;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Discovery/TestCase.php';
require_once __DIR__ . '/Discovery/TestCaseDiscovery.php';
require_once __DIR__ . '/Reporting/SummaryReport.php';
require_once __DIR__ . '/TestGroup/TestGroup.php';
require_once __DIR__ . '/TestGroup/TestGroupLoader.php';

$rootDir = dirname(__DIR__);
$testGroupsFile = $rootDir . '/src/test-groups.toml';
$testsDir = $rootDir . '/tests';
$resultsDir = $rootDir . '/results';

$loader = new TestGroupLoader();
$testGroups = $loader->load($testGroupsFile);
$discovery = new TestCaseDiscovery();
$testCases = $discovery->discover($testsDir, $testGroups);
$summaryReport = new SummaryReport();
$summaryPath = $resultsDir . '/results.html';
$tools = ['phpstan', 'phpstan-strict', 'psalm', 'mago', 'phan', 'noverify'];

$summaryReport->generate(
    resultsRoot: $resultsDir,
    outputPath: $summaryPath,
    testGroups: $testGroups,
    testCases: $testCases,
    tools: $tools,
);

printf("Generated summary report at %s\n", $summaryPath);
