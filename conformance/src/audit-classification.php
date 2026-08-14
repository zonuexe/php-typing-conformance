<?php

declare(strict_types=1);

/**
 * One-shot classification audit: test-method gaps vs result-classification gaps.
 *
 * Not part of the published report. Run:
 *   php conformance/src/audit-classification.php
 */

use Conformance\Discovery\TestCaseDiscovery;
use Conformance\Expectation\ExpectationParser;
use Conformance\Result\ResultRepository;
use Conformance\TestGroup\TestGroupLoader;
use Internal\Toml\Toml;

require_once __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);
$testsDir = $rootDir . '/tests';
$resultsDir = $rootDir . '/results';

$loader = new TestGroupLoader();
$testGroups = $loader->load($rootDir . '/src/test-groups.toml');
$discovery = new TestCaseDiscovery();
$testCases = $discovery->discover($testsDir, $testGroups);
$parser = new ExpectationParser();
$repo = new ResultRepository($resultsDir);

$toolDirs = array_values(array_filter(
    scandir($resultsDir) ?: [],
    static function (string $name) use ($resultsDir): bool {
        return $name !== '.' && $name !== '..'
            && is_dir($resultsDir . '/' . $name)
            && $name !== 'lsp' && $name !== 'tests' && $name !== 'scaffold';
    },
));

/**
 * @return array{kind: string, hasT: bool, requiredE: int, optionalE: int, quiet: int, noise: int, groups: int, unmarkedCalls: int, lines: list<string>}
 */
function inspectTest(string $path, ExpectationParser $parser): array
{
    $expected = $parser->parseFile($path);
    $markers = $parser->parseTypeMarkers($path);
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

    $requiredE = 0;
    $optionalE = 0;
    $quiet = 0;
    $noise = 0;
    $groups = 0;
    $eLines = [];

    foreach ($expected as $d) {
        if ($d->tag === 'noise') {
            $noise++;
            $eLines[$d->line] = true;
            continue;
        }
        if ($d->tag !== null) {
            $groups++;
            $eLines[$d->line] = true;
            continue;
        }
        if ($d->quiet) {
            $quiet++;
            $eLines[$d->line] = true;
            continue;
        }
        if ($d->required) {
            $requiredE++;
        } else {
            $optionalE++;
        }
        $eLines[$d->line] = true;
    }

    $unmarkedCalls = 0;
    foreach ($lines as $i => $line) {
        $lineNo = $i + 1;
        if (isset($eLines[$lineNo])) {
            continue;
        }
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '//') || str_starts_with($trim, '*')
            || str_starts_with($trim, '/*') || str_starts_with($trim, '<?')
            || str_starts_with($trim, 'namespace ') || str_starts_with($trim, 'use ')
            || str_starts_with($trim, 'function ') || str_starts_with($trim, 'class ')
            || str_starts_with($trim, 'interface ') || str_starts_with($trim, 'trait ')
            || str_starts_with($trim, 'enum ') || str_starts_with($trim, '{')
            || str_starts_with($trim, '}') || str_starts_with($trim, 'return ')
            || str_starts_with($trim, 'if ') || str_starts_with($trim, 'if(')
            || str_starts_with($trim, 'throw ') || str_starts_with($trim, 'echo ')
            || str_starts_with($trim, '/**') || $trim === '*/'
        ) {
            continue;
        }
        // Call-like or assignment that is not a declaration.
        if (preg_match('/\w\s*\(|=\s*/', $trim) === 1) {
            $unmarkedCalls++;
        }
    }

    $kind = 'soundness';
    $src = implode("\n", $lines);
    if (preg_match('/@conformance-kind:?\s+([\w-]+)/', $src, $m) === 1) {
        $kind = strtolower($m[1]);
    }

    return [
        'kind' => $kind,
        'hasT' => $markers !== [],
        'requiredE' => $requiredE,
        'optionalE' => $optionalE,
        'quiet' => $quiet,
        'noise' => $noise,
        'groups' => $groups,
        'unmarkedCalls' => $unmarkedCalls,
        'lines' => $lines,
        'expected' => $expected,
        'markers' => $markers,
    ];
}

$tests = [];
foreach ($testCases as $tc) {
    $tests[$tc->name] = inspectTest($tc->path, $parser) + ['group' => $tc->groupKey, 'path' => $tc->path];
}

echo "=== TEST METHOD (corpus) ===\n";
$total = count($tests);
$withT = 0;
$noProbes = [];
$onlyOptional = [];
$noValidControlT = [];
$noValidControlPF = [];
$requiredOnlyT = [];
$debug = 0;
$style = 0;
$tWithRequired = 0;
$tWithOptionalOnly = 0;
$quietTests = [];

foreach ($tests as $name => $info) {
    if ($info['kind'] === 'debug') {
        $debug++;
    }
    if ($info['kind'] === 'style') {
        $style++;
    }
    if ($info['hasT']) {
        $withT++;
        $probeCount = $info['requiredE'] + $info['optionalE'] + $info['quiet'] + $info['groups'];
        if ($probeCount === 0) {
            $noProbes[] = $name;
        }
        if ($info['requiredE'] === 0 && $info['optionalE'] > 0 && $info['groups'] === 0) {
            $tWithOptionalOnly++;
        }
        if ($info['requiredE'] > 0) {
            $tWithRequired++;
        }
        if ($info['unmarkedCalls'] === 0 && $info['quiet'] === 0 && $probeCount > 0) {
            $noValidControlT[] = $name;
        }
    } else {
        $probeCount = $info['requiredE'] + $info['optionalE'] + $info['groups'];
        if ($info['requiredE'] === 0 && $probeCount > 0 && $info['kind'] === 'soundness') {
            $onlyOptional[] = $name;
        }
        if ($info['unmarkedCalls'] === 0 && $info['quiet'] === 0 && $info['requiredE'] > 0 && $info['kind'] === 'soundness') {
            $noValidControlPF[] = $name;
        }
    }
    if ($info['quiet'] > 0) {
        $quietTests[] = $name;
    }
}

echo "tests: {$total}  T-rows: {$withT}  debug: {$debug}  style: {$style}\n";
echo "T with required E: {$tWithRequired}  T with optional-only E: {$tWithOptionalOnly}\n";
echo "T no-probes: " . count($noProbes) . "\n";
if ($noProbes !== []) {
    echo "  " . implode(', ', $noProbes) . "\n";
}
echo "T with probes but no unmarked valid-call / Q control: " . count($noValidControlT) . "\n";
if ($noValidControlT !== []) {
    echo "  " . implode("\n  ", $noValidControlT) . "\n";
}
echo "Pass/Fail soundness with only optional E (silence=Pass): " . count($onlyOptional) . "\n";
if ($onlyOptional !== []) {
    echo "  " . implode(', ', $onlyOptional) . "\n";
}
echo "Pass/Fail soundness with required E but no valid control: " . count($noValidControlPF) . "\n";
if ($noValidControlPF !== []) {
    echo "  " . implode("\n  ", $noValidControlPF) . "\n";
}
echo "tests using // Q: " . count($quietTests) . " — " . implode(', ', $quietTests) . "\n";

echo "\n=== RESULT CLASSIFICATION ===\n";

$combo = [];
$incidentalEnforced = []; // unrecognized + enforced
$enforcedWithFp = []; // recognized + enforced + FP
$recognizedNoneUnknown = [];
$silentPassOptional = [];
$tLineNonRecognition = [];
$eLineSuspicious = [];
$failDueToFp = [];
$emptyOutputPass = [];
$notMeasured = [];

$recognitionLooksLikeType = static function (string $msg): bool {
    return preg_match(
        '/\b(unresolvable|undeclared type|unknown type|undefined type|does not exist|not found|invalid type|parse error|phpdoc|docblock|unrecognized|not a valid|cannot (?:parse|resolve)|non-existent|undefined class|use of unknown class|phanundeclaredtype|missingType|parameter\.unresolvable|return\.unresolvable|varTag\.unresolvable)\b/i',
        $msg,
    ) === 1;
};

$looksLikeStyleOrUnused = static function (string $msg): bool {
    return preg_match(
        '/\b(unused|never (?:read|used)|not used|dead code|has no effect|does not do anything|missingType\.(?:parameter|return|property)|implicitly nullable|deprecated|todo|fixme)\b/i',
        $msg,
    ) === 1;
};

foreach ($toolDirs as $tool) {
    foreach ($tests as $name => $info) {
        $result = $repo->loadResult($tool, $name);
        if ($result === []) {
            continue;
        }
        $auto = (string) ($result['conformance_automated'] ?? '');
        $rec = $result['recognition'] ?? null;
        $enf = $result['enforcement'] ?? null;
        $fp = $result['false_positive_lines'] ?? [];
        $unrec = $result['unrecognized_lines'] ?? [];
        $output = (string) ($result['output'] ?? '');
        $status = (string) ($result['status'] ?? 'Unknown');

        if ($auto === 'Not measured') {
            $notMeasured[] = "{$tool}/{$name}";
            continue;
        }

        $key = ($rec === null ? 'PF' : $rec) . '+' . ($enf ?? $auto);
        $combo[$key] = ($combo[$key] ?? 0) + 1;

        if ($rec === 'unrecognized' && $enf === 'enforced') {
            $incidentalEnforced[] = "{$tool}/{$name} " . ($result['enforced_lines'] ?? '')
                . ' fp=' . json_encode($fp);
        }
        $over = $result['over_rejected_lines'] ?? [];
        if ($rec === 'recognized' && is_array($over) && $over !== []) {
            $enforcedWithFp[] = "{$tool}/{$name} over=" . json_encode($over) . ' enf=' . (string) $enf;
        }
        if ($rec === 'recognized' && $enf === 'none' && $status === 'Unknown') {
            $recognizedNoneUnknown[] = "{$tool}/{$name}";
        }
        if ($auto === 'Pass' && $output === '' && $info['requiredE'] === 0
            && ($info['optionalE'] > 0 || $info['groups'] > 0) && $info['kind'] === 'soundness') {
            $silentPassOptional[] = "{$tool}/{$name}";
        }
        if ($auto === 'Pass' && $output === '' && !$info['hasT'] && $info['kind'] === 'soundness'
            && $info['requiredE'] === 0) {
            $emptyOutputPass[] = "{$tool}/{$name}";
        }
        if ($info['hasT'] && $auto === 'Fail' && is_array($fp) && $fp !== [] && $rec === 'recognized') {
            $failDueToFp[] = "{$tool}/{$name} fp=" . json_encode($fp);
        }

        // Parse output lines: file:line: message
        if ($output !== '' && $info['hasT']) {
            foreach (preg_split('/\r?\n/', $output) ?: [] as $outLine) {
                if (preg_match('/:(\d+):\s*(.*)$/', $outLine, $m) !== 1) {
                    continue;
                }
                $lineNo = (int) $m[1];
                $msg = $m[2];
                $isT = false;
                foreach ($info['markers'] as $marker) {
                    if ($marker->line === $lineNo) {
                        $isT = true;
                        break;
                    }
                }
                if ($isT && !$recognitionLooksLikeType($msg) && !$looksLikeStyleOrUnused($msg) === false) {
                    // keep both buckets
                }
                if ($isT && !$recognitionLooksLikeType($msg)) {
                    $tLineNonRecognition[] = "{$tool}/{$name}:{$lineNo} {$msg}";
                }
            }
        }
    }
}

echo "combination counts:\n";
ksort($combo);
foreach ($combo as $k => $n) {
    echo sprintf("  %-40s %d\n", $k, $n);
}

echo "\nunrecognized+enforced (incidental; matrix says Unrecognized): " . count($incidentalEnforced) . "\n";
foreach (array_slice($incidentalEnforced, 0, 40) as $row) {
    echo "  {$row}\n";
}
if (count($incidentalEnforced) > 40) {
    echo "  ... +" . (count($incidentalEnforced) - 40) . " more\n";
}

echo "\nrecognized+enforced WITH false positives (matrix says Enforced!): " . count($enforcedWithFp) . "\n";
foreach ($enforcedWithFp as $row) {
    echo "  {$row}\n";
}

echo "\nrecognized+none, status Unknown (Not enforced, no curated fallback): " . count($recognizedNoneUnknown) . "\n";
// too many — just count by tool
$byTool = [];
foreach ($recognizedNoneUnknown as $row) {
    $tool = explode('/', $row, 2)[0];
    $byTool[$tool] = ($byTool[$tool] ?? 0) + 1;
}
foreach ($byTool as $t => $n) {
    echo "  {$t}: {$n}\n";
}

echo "\nT-line diagnostics that do NOT look like type-resolution failures: " . count($tLineNonRecognition) . "\n";
foreach (array_slice($tLineNonRecognition, 0, 50) as $row) {
    echo "  {$row}\n";
}
if (count($tLineNonRecognition) > 50) {
    echo "  ... +" . (count($tLineNonRecognition) - 50) . " more\n";
}

echo "\nrecognized Fail due to FP on valid lines: " . count($failDueToFp) . "\n";
foreach (array_slice($failDueToFp, 0, 30) as $row) {
    echo "  {$row}\n";
}

echo "\nsilent Pass on optional-only soundness: " . count($silentPassOptional) . "\n";
foreach ($silentPassOptional as $row) {
    echo "  {$row}\n";
}

echo "\nempty-output Pass on non-T soundness with no required E: " . count($emptyOutputPass) . "\n";
foreach ($emptyOutputPass as $row) {
    echo "  {$row}\n";
}

echo "\nnot measured: " . count($notMeasured) . "\n";
