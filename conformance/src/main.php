<?php

declare(strict_types=1);

use Conformance\TestGroup\TestGroupLoader;
use Conformance\Checker\Checker;
use Conformance\Checker\PhanChecker;
use Conformance\Checker\PhpStanChecker;
use Conformance\Checker\PsalmChecker;
use Conformance\Checker\MagoChecker;
use Conformance\Checker\MirChecker;
use Conformance\Checker\IntelephenseChecker;
use Conformance\Checker\NoVerifyChecker;
use Conformance\Checker\PhpyChecker;
use Conformance\Checker\PzoomChecker;
use Conformance\Checker\SteinsChecker;
use Conformance\Discovery\TestCaseDiscovery;
use Conformance\Expectation\ExpectationEvaluator;
use Conformance\Expectation\ExpectationParser;
use Conformance\Expectation\ExpectedDiagnostic;
use Conformance\Result\ResultRecord;
use Conformance\Result\ResultRepository;
use Conformance\Reporting\SummaryReport;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Checker/Checker.php';
require_once __DIR__ . '/Checker/MagoChecker.php';
require_once __DIR__ . '/Checker/MirChecker.php';
require_once __DIR__ . '/Checker/IntelephenseChecker.php';
require_once __DIR__ . '/Checker/NoVerifyChecker.php';
require_once __DIR__ . '/Checker/PhanChecker.php';
require_once __DIR__ . '/Checker/PhpyChecker.php';
require_once __DIR__ . '/Checker/PhpStanChecker.php';
require_once __DIR__ . '/Checker/PsalmChecker.php';
require_once __DIR__ . '/Checker/PzoomChecker.php';
require_once __DIR__ . '/Checker/SteinsChecker.php';
require_once __DIR__ . '/Discovery/TestCase.php';
require_once __DIR__ . '/Discovery/TestCaseDiscovery.php';
require_once __DIR__ . '/Expectation/ExpectedDiagnostic.php';
require_once __DIR__ . '/Expectation/ExpectationEvaluation.php';
require_once __DIR__ . '/Expectation/ExpectationEvaluator.php';
require_once __DIR__ . '/Expectation/ExpectationParser.php';
require_once __DIR__ . '/Expectation/TypeHandling.php';
require_once __DIR__ . '/Expectation/TypeMarker.php';
require_once __DIR__ . '/Result/ResultRecord.php';
require_once __DIR__ . '/Result/ResultRepository.php';
require_once __DIR__ . '/Reporting/SummaryReport.php';
require_once __DIR__ . '/TestGroup/TestGroup.php';
require_once __DIR__ . '/TestGroup/TestGroupLoader.php';

/**
 * Lowest PHPStan level whose rules report one of the diagnostics this test
 * expects.
 *
 * Deliberately restricted to the expected lines: PHPStan levels turn rule sets
 * on, they do not change type inference, so the only level worth reporting is
 * the one that gates the rule the test is about. Unexpected noise elsewhere in
 * the file (a `missingType.*` complaint at level 6, say) says nothing about the
 * behaviour under test.
 *
 * @param list<ExpectedDiagnostic> $expectedDiagnostics
 */
function expectedDiagnosticLevel(PhpStanChecker $checker, array $expectedDiagnostics): ?int
{
    $lineLevels = $checker->lineLevels();
    $levels = [];

    foreach ($expectedDiagnostics as $diagnostic) {
        if ($diagnostic->tool !== null && $diagnostic->tool !== $checker->name()) {
            continue;
        }

        if (isset($lineLevels[$diagnostic->line])) {
            $levels[] = $lineLevels[$diagnostic->line];
        }
    }

    return $levels === [] ? null : min($levels);
}

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
    resolveDiagnosticLevels: true,
);
$phpStanStrictChecker = new PhpStanChecker(
    toolName: 'phpstan-strict',
    binaryPath: $projectRoot . '/vendor-bin/phpstan/vendor/bin/phpstan',
    configPath: $phpStanConfigPath,
    resolveDiagnosticLevels: false,
);
$magoChecker = new MagoChecker(
    binaryPath: $projectRoot . '/vendor-bin/mago/vendor/bin/mago',
    workspacePath: $rootDir,
);
$mirChecker = new MirChecker(
    binaryPath: $projectRoot . '/vendor-bin/mir/vendor/bin/mir',
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
$intelephenseChecker = new IntelephenseChecker(
    nodeBinary: 'node',
    serverPath: $projectRoot . '/vendor-bin/intelephense/node_modules/intelephense/lib/intelephense.js',
    clientPath: __DIR__ . '/Checker/intelephense-client.mjs',
    packageJsonPath: $projectRoot . '/vendor-bin/intelephense/node_modules/intelephense/package.json',
);
$steinsChecker = new SteinsChecker();
$pzoomChecker = new PzoomChecker(
    configPath: $psalmConfigPath,
);
$phpyChecker = new PhpyChecker(
    nodeBinary: 'node',
    cliPath: $projectRoot . '/vendor-bin/phpy/node_modules/phpy/dist/index.js',
);
$checkers = [$phanChecker, $phpStanChecker, $phpStanStrictChecker, $psalmChecker, $pzoomChecker, $magoChecker, $mirChecker, $noVerifyChecker, $intelephenseChecker, $phpyChecker, $steinsChecker];

// Optional `--tool NAME` / `--tool=NAME` filter: run and persist only the
// selected checker(s), leaving every other tool's results untouched. Accepts a
// comma-separated list. When a filter is active the HTML summary report is left
// alone (regenerating it with a partial tool set would drop the other columns).
$toolFilter = null;
$argvValues = $argv ?? [];
for ($i = 1, $argc = count($argvValues); $i < $argc; $i++) {
    $arg = $argvValues[$i];
    if ($arg === '--tool' && isset($argvValues[$i + 1])) {
        $toolFilter = $argvValues[$i + 1];
        $i++;
    } elseif (str_starts_with($arg, '--tool=')) {
        $toolFilter = substr($arg, strlen('--tool='));
    }
}

$reportFilterActive = false;
if ($toolFilter !== null) {
    $selected = array_values(array_filter(array_map('trim', explode(',', $toolFilter)), static fn (string $n): bool => $n !== ''));
    $checkers = array_values(array_filter(
        $checkers,
        static fn (Checker $checker): bool => in_array($checker->name(), $selected, true),
    ));

    if ($checkers === []) {
        fwrite(STDERR, sprintf("No checker matched --tool filter '%s'\n", $toolFilter));
        exit(1);
    }

    $reportFilterActive = true;
    printf("Tool filter active: running only [%s]\n", implode(', ', array_map(static fn (Checker $c): string => $c->name(), $checkers)));
}

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
    $typeMarkers = $expectationParser->parseTypeMarkers($testCase->path);

    printf(
        "- %s (%s): %d expectation(s), %d type marker(s)\n",
        $testCase->fileName,
        $testCase->groupKey,
        count($expectedDiagnostics),
        count($typeMarkers),
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

        $evaluation = $expectationEvaluator->evaluate(
            $expectedDiagnostics,
            $diagnostics,
            $checker->name(),
            $typeMarkers,
        );
        printf("  %s automated: %s\n", $checker->name(), $evaluation->conformanceAutomated);

        if ($evaluation->typeHandling !== null) {
            printf(
                "  %s handling: %s / %s (%d of %d expected lines enforced)\n",
                $checker->name(),
                $evaluation->typeHandling->recognition,
                $evaluation->typeHandling->enforcement,
                $evaluation->typeHandling->enforcedLineCount,
                $evaluation->typeHandling->expectedLineCount,
            );
        }

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
            expectedDiagnosticLevel: $checker instanceof PhpStanChecker
                ? expectedDiagnosticLevel($checker, $expectedDiagnostics)
                : null,
            output: implode("\n", $outputLines),
            errorsDiff: $evaluation->errorsDiff,
            notes: '',
            ignoreErrors: [],
            expectedDiagnosticCount: count($expectedDiagnostics),
            typeHandling: $evaluation->typeHandling,
        );

        $resultPath = $resultRepository->save($record);
        printf("  wrote %s\n", $resultPath);
    }
}

foreach ($checkers as $checker) {
    $versionPath = $resultRepository->saveVersion($checker->name(), $checker->version());
    printf("Saved %s version to %s\n", $checker->name(), $versionPath);
}

if ($reportFilterActive) {
    printf("Tool filter active: skipping HTML summary report regeneration to preserve other tools' columns.\n");
    return;
}

$summaryPath = $resultsDir . '/results.html';
// phpstan-strict is still run above for its data, but the report merges it into
// the phpstan column, so it is not listed as its own display column.
$reportTools = array_values(array_filter(
    array_map(static fn ($checker): string => $checker->name(), $checkers),
    static fn (string $name): bool => $name !== 'phpstan-strict' && $name !== 'pzoom',
));
$summaryReport->generate(
    resultsRoot: $resultsDir,
    outputPath: $summaryPath,
    testGroups: $testGroups,
    testCases: $testCases,
    tools: $reportTools,
);

printf("Generated summary report at %s\n", $summaryPath);
