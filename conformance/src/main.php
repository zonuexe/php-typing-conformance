<?php

declare(strict_types=1);

use Conformance\TestGroup\TestGroupLoader;
use Conformance\Checker\PhpStanChecker;
use Conformance\Discovery\TestCaseDiscovery;
use Conformance\Expectation\ExpectationParser;
use Conformance\Result\ResultRecord;
use Conformance\Result\ResultRepository;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Checker/Checker.php';
require_once __DIR__ . '/Checker/PhpStanChecker.php';
require_once __DIR__ . '/Discovery/TestCase.php';
require_once __DIR__ . '/Discovery/TestCaseDiscovery.php';
require_once __DIR__ . '/Expectation/ExpectedDiagnostic.php';
require_once __DIR__ . '/Expectation/ExpectationParser.php';
require_once __DIR__ . '/Result/ResultRecord.php';
require_once __DIR__ . '/Result/ResultRepository.php';
require_once __DIR__ . '/TestGroup/TestGroup.php';
require_once __DIR__ . '/TestGroup/TestGroupLoader.php';

$rootDir = dirname(__DIR__);
$testGroupsFile = $rootDir . '/src/test-groups.toml';
$testsDir = $rootDir . '/tests';
$resultsDir = $rootDir . '/results';
$projectRoot = dirname($rootDir);

$loader = new TestGroupLoader();
$testGroups = $loader->load($testGroupsFile);
$discovery = new TestCaseDiscovery();
$testCases = $discovery->discover($testsDir, $testGroups);
$expectationParser = new ExpectationParser();
$resultRepository = new ResultRepository($resultsDir);
$phpStanChecker = new PhpStanChecker(
    projectRoot: $projectRoot,
    binaryPath: $projectRoot . '/vendor-bin/phpstan/vendor/bin/phpstan',
);

printf("Loaded %d test groups\n", count($testGroups));

foreach ($testGroups as $group) {
    printf(
        "- %s [%s]: %s\n",
        $group->key,
        $group->sourceCategory,
        $group->name,
    );
}

printf("\nDiscovered %d test case(s)\n", count($testCases));

foreach ($testCases as $testCase) {
    $expectedDiagnostics = $expectationParser->parseFile($testCase->path);

    printf(
        "- %s (%s): %d expectation(s)\n",
        $testCase->fileName,
        $testCase->groupKey,
        count($expectedDiagnostics),
    );

    foreach ($expectedDiagnostics as $diagnostic) {
        $kind = $diagnostic->required ? 'required' : 'optional';
        $tag = $diagnostic->tag !== null ? sprintf(', tag=%s', $diagnostic->tag) : '';
        $multi = $diagnostic->allowMultiple ? ', multiple' : '';

        printf(
            "  line %d: %s%s%s\n",
            $diagnostic->line,
            $kind,
            $tag,
            $multi,
        );
    }

    $phpStanDiagnostics = $phpStanChecker->analyse($testCase);
    printf("  phpstan: %d diagnostic line(s)\n", count($phpStanDiagnostics));

    $outputLines = [];
    foreach ($phpStanDiagnostics as $lineNumber => $messages) {
        foreach ($messages as $message) {
            $outputLines[] = sprintf('%s:%d: %s', $testCase->fileName, $lineNumber, $message);
        }
    }

    $record = new ResultRecord(
        tool: $phpStanChecker->name(),
        testName: $testCase->name,
        status: 'Unknown',
        conformanceAutomated: 'Unknown',
        output: implode("\n", $outputLines),
        errorsDiff: '',
        notes: '',
        ignoreErrors: [],
        expectedDiagnosticCount: count($expectedDiagnostics),
    );

    $resultPath = $resultRepository->save($record);
    printf("  wrote %s\n", $resultPath);
}
