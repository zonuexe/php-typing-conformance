<?php

declare(strict_types=1);

use Conformance\TestGroup\TestGroupLoader;
use Conformance\Checker\Checker;
use Conformance\Checker\CoverageAware;
use Conformance\Checker\PhanChecker;
use Conformance\Checker\PhpStanChecker;
use Conformance\Checker\PsalmChecker;
use Conformance\Checker\QodanaChecker;
use Conformance\Checker\MagoChecker;
use Conformance\Checker\PhpantomChecker;
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
use Conformance\Result\ResultsUpdate;
use Conformance\Reporting\Report;

require_once __DIR__ . '/../vendor/autoload.php';

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
$phpStanChecker = new PhpStanChecker(
    toolName: 'phpstan',
    binaryPath: $projectRoot . '/vendor-bin/phpstan/vendor/bin/phpstan',
    configPath: $phpStanNoStrictConfigPath,
    testsDir: $testsDir,
    resolveDiagnosticLevels: true,
);
$phpStanStrictChecker = new PhpStanChecker(
    toolName: 'phpstan-strict',
    binaryPath: $projectRoot . '/vendor-bin/phpstan/vendor/bin/phpstan',
    configPath: $phpStanConfigPath,
    testsDir: $testsDir,
    resolveDiagnosticLevels: false,
);
$magoChecker = new MagoChecker(
    binaryPath: $projectRoot . '/vendor-bin/mago/vendor/bin/mago',
    workspacePath: $rootDir,
);
$phpantomChecker = new PhpantomChecker(
    binaryPath: $projectRoot . '/vendor-bin/phpantom/bin/phpantom_lsp',
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
$psalmNextChecker = new PsalmChecker(
    projectRoot: $projectRoot,
    binaryPath: $projectRoot . '/vendor-bin/psalm-next/vendor/bin/psalm',
    configPath: $psalmConfigPath,
    toolName: 'psalm-next',
);
$intelephenseChecker = new IntelephenseChecker(
    nodeBinary: 'node',
    serverPath: $projectRoot . '/vendor-bin/intelephense/node_modules/intelephense/lib/intelephense.js',
    clientPath: __DIR__ . '/Checker/intelephense-client.mjs',
    packageJsonPath: $projectRoot . '/vendor-bin/intelephense/node_modules/intelephense/package.json',
);
$steinsChecker = new SteinsChecker(
    binaryPath: $projectRoot . '/vendor-bin/steins/vendor/bin/steins',
);
$pzoomChecker = new PzoomChecker(
    configPath: $psalmConfigPath,
);
$qodanaChecker = new QodanaChecker(
    projectRoot: $projectRoot,
);
$phpyChecker = new PhpyChecker(
    nodeBinary: 'node',
    cliPath: $projectRoot . '/vendor-bin/phpy/node_modules/phpy/dist/index.js',
);
$checkers = [$phanChecker, $phpStanChecker, $phpStanStrictChecker, $psalmChecker, $psalmNextChecker, $pzoomChecker, $magoChecker, $phpantomChecker, $mirChecker, $noVerifyChecker, $intelephenseChecker, $phpyChecker, $steinsChecker, $qodanaChecker];

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
        $mode = $diagnostic->quiet ? 'quiet' : 'report';
        $tool = $diagnostic->tool !== null ? sprintf(', tool=%s', $diagnostic->tool) : '';
        $tag = $diagnostic->tag !== null ? sprintf(', tag=%s', $diagnostic->tag) : '';
        $multi = $diagnostic->allowMultiple ? ', multiple' : '';

        printf(
            "  line %d: %s %s%s%s%s\n",
            $diagnostic->line,
            $kind,
            $mode,
            $tool,
            $tag,
            $multi,
        );
    }

    foreach ($checkers as $checker) {
        // A checker whose results are produced by hand can be older than the
        // corpus. Recording an empty diagnostic list for a test it never saw
        // would enter the matrix as a clean pass, so say so instead.
        if ($checker instanceof CoverageAware && !$checker->covers($testCase)) {
            $gap = $checker->coverageGap($testCase);
            printf("  %s: not measured (%s)\n", $checker->name(), $gap);

            $resultPath = $resultRepository->save(new ResultRecord(
                tool: $checker->name(),
                testName: $testCase->name,
                status: 'Unknown',
                conformanceAutomated: 'Not measured',
                expectedDiagnosticLevel: null,
                output: '',
                errorsDiff: '',
                notes: $gap,
                ignoreErrors: [],
                expectedDiagnosticCount: count($expectedDiagnostics),
            ));
            printf("  wrote %s\n", $resultPath);

            continue;
        }

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

// Stamp the data, unless this run re-derived exactly what was already there:
// the report says when the results last changed, not when the suite last ran.
$resultsUpdate = new ResultsUpdate($resultsDir, $testsDir);
$previousUpdate = $resultsUpdate->recorded();
$currentUpdate = $resultsUpdate->record();

printf(
    $currentUpdate === $previousUpdate
        ? "Nothing changed; the update stamp stays at %s\n"
        : "Recorded the update at %s\n",
    $currentUpdate,
);

if ($reportFilterActive) {
    printf("Tool filter active: skipping HTML summary report regeneration to preserve other tools' columns.\n");
    return;
}

// The columns are the tools that just ran, minus the two the report merges
// into another column: phpstan-strict into phpstan, pzoom into psalm.
$reportTools = array_values(array_filter(
    array_map(static fn ($checker): string => $checker->name(), $checkers),
    static fn (string $name): bool => $name !== 'phpstan-strict' && $name !== 'pzoom',
));

printf("Generated summary report at %s\n", Report::fromRootDir($rootDir, $reportTools)->write());
