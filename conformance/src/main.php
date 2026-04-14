<?php

declare(strict_types=1);

use Conformance\TestGroup\TestGroupLoader;
use Conformance\Checker\PhanChecker;
use Conformance\Checker\PhpStanChecker;
use Conformance\Checker\PsalmChecker;
use Conformance\Checker\MagoChecker;
use Conformance\Checker\NoVerifyChecker;
use Conformance\Discovery\TestCaseDiscovery;
use Conformance\Expectation\ExpectationEvaluator;
use Conformance\Expectation\ExpectationParser;
use Conformance\Result\ResultRecord;
use Conformance\Result\ResultRepository;
use Conformance\Reporting\SummaryReport;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Checker/Checker.php';
require_once __DIR__ . '/Checker/MagoChecker.php';
require_once __DIR__ . '/Checker/NoVerifyChecker.php';
require_once __DIR__ . '/Checker/PhanChecker.php';
require_once __DIR__ . '/Checker/PhpStanChecker.php';
require_once __DIR__ . '/Checker/PsalmChecker.php';
require_once __DIR__ . '/Discovery/TestCase.php';
require_once __DIR__ . '/Discovery/TestCaseDiscovery.php';
require_once __DIR__ . '/Expectation/ExpectedDiagnostic.php';
require_once __DIR__ . '/Expectation/ExpectationEvaluation.php';
require_once __DIR__ . '/Expectation/ExpectationEvaluator.php';
require_once __DIR__ . '/Expectation/ExpectationParser.php';
require_once __DIR__ . '/Result/ResultRecord.php';
require_once __DIR__ . '/Result/ResultRepository.php';
require_once __DIR__ . '/Reporting/SummaryReport.php';
require_once __DIR__ . '/TestGroup/TestGroup.php';
require_once __DIR__ . '/TestGroup/TestGroupLoader.php';

$rootDir = dirname(__DIR__);
$testGroupsFile = $rootDir . '/src/test-groups.toml';
$testsDir = $rootDir . '/tests';
$resultsDir = $rootDir . '/results';
$projectRoot = dirname($rootDir);
$phpStanConfigPath = $rootDir . '/phpstan.dist.neon';
$phpStanNoStrictConfigPath = $rootDir . '/phpstan-no-strict.neon';
$psalmConfigPath = $rootDir . '/psalm.xml';

$loader = new TestGroupLoader();
$testGroups = $loader->load($testGroupsFile);
$discovery = new TestCaseDiscovery();
$testCases = $discovery->discover($testsDir, $testGroups);
$expectationEvaluator = new ExpectationEvaluator();
$expectationParser = new ExpectationParser();
$resultRepository = new ResultRepository($resultsDir);
$summaryReport = new SummaryReport();
$phpStanChecker = new PhpStanChecker(
    toolName: 'phpstan',
    binaryPath: $projectRoot . '/vendor-bin/phpstan/vendor/bin/phpstan',
    configPath: $phpStanNoStrictConfigPath,
    stopAtFirstDetectedLevel: true,
);
$phpStanStrictChecker = new PhpStanChecker(
    toolName: 'phpstan-strict',
    binaryPath: $projectRoot . '/vendor-bin/phpstan/vendor/bin/phpstan',
    configPath: $phpStanConfigPath,
    stopAtFirstDetectedLevel: false,
);
$magoChecker = new MagoChecker(
    binaryPath: $projectRoot . '/vendor-bin/mago/vendor/bin/mago',
    workspacePath: $rootDir,
);
$noVerifyChecker = new NoVerifyChecker(
    binaryPath: $projectRoot . '/vendor/bin/noverify',
);
$phanChecker = new PhanChecker(
    binaryPath: $projectRoot . '/vendor-bin/phan/vendor/bin/phan',
    testsDir: $rootDir . '/tests',
);
$psalmChecker = new PsalmChecker(
    projectRoot: $projectRoot,
    binaryPath: $projectRoot . '/vendor-bin/psalm/vendor/bin/psalm',
    configPath: $psalmConfigPath,
);
$checkers = [$phpStanChecker, $phpStanStrictChecker, $psalmChecker, $magoChecker, $phanChecker, $noVerifyChecker];

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
        $tool = $diagnostic->tool !== null ? sprintf(', tool=%s', $diagnostic->tool) : '';
        $tag = $diagnostic->tag !== null ? sprintf(', tag=%s', $diagnostic->tag) : '';
        $multi = $diagnostic->allowMultiple ? ', multiple' : '';

        printf(
            "  line %d: %s%s%s%s\n",
            $diagnostic->line,
            $kind,
            $tool,
            $tag,
            $multi,
        );
    }

    foreach ($checkers as $checker) {
        $diagnostics = $checker->analyse($testCase);
        printf("  %s: %d diagnostic line(s)\n", $checker->name(), count($diagnostics));

        $evaluation = $expectationEvaluator->evaluate($expectedDiagnostics, $diagnostics, $checker->name());
        printf("  %s automated: %s\n", $checker->name(), $evaluation->conformanceAutomated);

        $outputLines = [];
        foreach ($diagnostics as $lineNumber => $messages) {
            foreach ($messages as $message) {
                $outputLines[] = sprintf('%s:%d: %s', $testCase->fileName, $lineNumber, $message);
            }
        }

        $record = new ResultRecord(
            tool: $checker->name(),
            testName: $testCase->name,
            status: 'Unknown',
            conformanceAutomated: $evaluation->conformanceAutomated,
            firstDetectedLevel: $checker instanceof PhpStanChecker ? $checker->firstDetectedLevel() : null,
            output: implode("\n", $outputLines),
            errorsDiff: $evaluation->errorsDiff,
            notes: '',
            ignoreErrors: [],
            expectedDiagnosticCount: count($expectedDiagnostics),
        );

        $resultPath = $resultRepository->save($record);
        printf("  wrote %s\n", $resultPath);
    }
}

foreach ($checkers as $checker) {
    $versionPath = $resultRepository->saveVersion($checker->name(), $checker->version());
    printf("Saved %s version to %s\n", $checker->name(), $versionPath);
}

$summaryPath = $resultsDir . '/results.html';
$summaryReport->generate(
    resultsRoot: $resultsDir,
    outputPath: $summaryPath,
    testGroups: $testGroups,
    testCases: $testCases,
    tools: array_map(static fn ($checker): string => $checker->name(), $checkers),
);

printf("Generated summary report at %s\n", $summaryPath);
