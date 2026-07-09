<?php

declare(strict_types=1);

/**
 * Corpus divergence sweep.
 *
 * Runs every analyzer over external test cases that belong to ONE analyzer's own suite,
 * IN AN ISOLATED STAGING WORKSPACE. Nothing is copied into this repository and no
 * third-party test code is redistributed: cases are read in place, staged into a scratch
 * directory for the duration of one analysis, then removed. Only the resulting behavioural
 * facts (which tool diverges on which case, by normalized category) are reported.
 *
 * Baseline verdict comes from the owning tool's inline expectation annotations
 * (currently Mago `@mago-expect analysis:<code>`); every other tool is compared against it.
 * Diagnostics are classified via categories.json and, by default, only `soundness`
 * categories are treated as real divergences (style/parser/heuristic noise is dropped).
 *
 * Usage:
 *   php conformance/corpus/sweep.php --cases-dir=<dir> [--all-categories] <case-name>...
 *   php conformance/corpus/sweep.php --cases-dir=/path/to/mago/crates/analyzer/tests/cases foo bar
 *
 * Case names are file stems (without .php). See conformance/corpus/README.md.
 */

use Conformance\Checker\PhanChecker;
use Conformance\Checker\PhpStanChecker;
use Conformance\Checker\PsalmChecker;
use Conformance\Checker\MagoChecker;
use Conformance\Checker\NoVerifyChecker;
use Conformance\Discovery\TestCase;

$repo = dirname(__DIR__, 2);
$src = $repo . '/conformance/src';
require_once $repo . '/vendor/autoload.php';
foreach ([
    '/Checker/Checker.php', '/Checker/MagoChecker.php', '/Checker/NoVerifyChecker.php',
    '/Checker/PhanChecker.php', '/Checker/PhpStanChecker.php', '/Checker/PsalmChecker.php',
    '/Discovery/TestCase.php',
] as $f) {
    require_once $src . $f;
}

// ---- args ----
$casesDir = null;
$onlySoundness = true;
$names = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--cases-dir=')) {
        $casesDir = substr($arg, strlen('--cases-dir='));
    } elseif ($arg === '--all-categories') {
        $onlySoundness = false;
    } else {
        $names[] = preg_replace('/\.php$/', '', $arg);
    }
}
if ($casesDir === null || !is_dir($casesDir) || $names === []) {
    fwrite(STDERR, "usage: sweep.php --cases-dir=<dir> [--all-categories] <case-name>...\n");
    exit(1);
}

// ---- classifier ----
$catData = json_decode((string) file_get_contents(__DIR__ . '/categories.json'), true);
$CATEGORIES = $catData['categories'];
$MAP = $catData['map'];
$unmapped = [];

/** extract a tool's diagnostic code from a formatted "message [code]" string */
function extractCode(string $tool, string $msg): string {
    if ($tool === 'phpstan' || $tool === 'phpstan-strict') {
        if (preg_match('/\[identifier=([^\]]+)\]/', $msg, $m)) { return $m[1]; }
        return '';
    }
    // psalm/mago/phan/noverify append the code as the last [bracket]
    if (preg_match('/\[([A-Za-z0-9_.-]+)\]\s*$/', $msg, $m)) { return $m[1]; }
    return '';
}

function classify(string $tool, string $code, array $MAP, array &$unmapped): string {
    $toolKey = ($tool === 'phpstan-strict') ? 'phpstan' : $tool;
    if ($code !== '' && isset($MAP[$toolKey][$code])) { return $MAP[$toolKey][$code]; }
    if ($code !== '') { $unmapped["$toolKey:$code"] = ($unmapped["$toolKey:$code"] ?? 0) + 1; }
    return 'unmapped';
}

// ---- staging workspace ----
$STG = sys_get_temp_dir() . '/corpus-sweep-' . getmypid();
@mkdir($STG . '/tests', 0777, true);
@mkdir($STG . '/vendor', 0777, true); // psalm ignoreFiles target must exist
copy($repo . '/conformance/psalm.xml', $STG . '/psalm.xml');
copy($repo . '/conformance/phpstan-no-strict.neon', $STG . '/phpstan-no-strict.neon');
copy($repo . '/conformance/phpstan.dist.neon', $STG . '/phpstan.dist.neon');
register_shutdown_function(static function () use ($STG): void {
    array_map('unlink', glob($STG . '/tests/*.php') ?: []);
    @unlink($STG . '/psalm.xml'); @unlink($STG . '/phpstan-no-strict.neon'); @unlink($STG . '/phpstan.dist.neon');
    @rmdir($STG . '/tests'); @rmdir($STG . '/vendor'); @rmdir($STG);
});

$checkers = [
    new PhpStanChecker('phpstan', $repo . '/vendor-bin/phpstan/vendor/bin/phpstan', $STG . '/phpstan-no-strict.neon', false),
    new PhpStanChecker('phpstan-strict', $repo . '/vendor-bin/phpstan/vendor/bin/phpstan', $STG . '/phpstan.dist.neon', false),
    new PsalmChecker($STG, $repo . '/vendor-bin/psalm/vendor/bin/psalm', $STG . '/psalm.xml'),
    new MagoChecker($repo . '/vendor-bin/mago/vendor/bin/mago', $STG),
    new PhanChecker($repo . '/vendor-bin/phan/vendor/bin/phan', $STG . '/tests'),
    new NoVerifyChecker($repo . '/vendor/bin/noverify'),
];
$compareTools = ['phpstan', 'phpstan-strict', 'psalm', 'phan']; // noverify excluded: shape-blind, always noisy

function magoExpect(string $file): array {
    $codes = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match_all('/@mago-expect\s+analysis:([a-z0-9-]+)/', $line, $m)) {
            foreach ($m[1] as $c) { $codes[$c] = true; }
        }
    }
    return array_keys($codes);
}

$divergences = [];
$processed = 0;
foreach ($names as $name) {
    $srcFile = rtrim($casesDir, '/') . '/' . $name . '.php';
    if (!is_file($srcFile)) { fwrite(STDERR, "missing: $srcFile\n"); continue; }
    array_map('unlink', glob($STG . '/tests/*.php') ?: []);
    $staged = $STG . '/tests/' . $name . '.php';
    copy($srcFile, $staged);

    $expectCodes = magoExpect($srcFile);
    $magoErr = $expectCodes !== [];
    $magoErrCats = [];
    foreach ($expectCodes as $c) { $magoErrCats[classify('mago', $c, $MAP, $unmapped)] = true; }
    $magoErrCats = array_keys(array_filter($magoErrCats, fn($k) => $CATEGORIES[$k]['soundness'] ?? false, ARRAY_FILTER_USE_KEY));

    $testCase = new TestCase($staged, $name . '.php', $name, '', []);
    foreach ($checkers as $checker) {
        if (!in_array($checker->name(), $compareTools, true)) { continue; }
        try { $diags = $checker->analyse($testCase); } catch (\Throwable $e) { continue; }

        $soundnessCats = [];
        foreach ($diags as $ln => $msgs) {
            foreach ($msgs as $msg) {
                $cat = classify($checker->name(), extractCode($checker->name(), $msg), $MAP, $unmapped);
                $isSound = $CATEGORIES[$cat]['soundness'] ?? false;
                if (!$onlySoundness || $isSound) {
                    $soundnessCats[$cat][] = "L$ln " . $msg;
                }
            }
        }
        $reportsSound = false;
        foreach ($soundnessCats as $cat => $_) { if ($CATEGORIES[$cat]['soundness'] ?? false) { $reportsSound = true; } }

        if (!$magoErr && $reportsSound) {
            $first = null;
            foreach ($soundnessCats as $cat => $items) { if ($CATEGORIES[$cat]['soundness'] ?? false) { $first = "[$cat] " . $items[0]; break; } }
            $divergences[] = ['kind' => 'FP', 'case' => $name, 'tool' => $checker->name(), 'detail' => $first];
        } elseif ($magoErr && !$reportsSound && $magoErrCats !== []) {
            $divergences[] = ['kind' => 'MISS', 'case' => $name, 'tool' => $checker->name(),
                'detail' => 'mago expects [' . implode(',', $magoErrCats) . '] but tool reports no soundness diagnostic'];
        }
    }
    $processed++;
}

// ---- report ----
echo "Corpus sweep: $processed case(s), " . ($onlySoundness ? 'soundness-only' : 'all-categories') . "\n";
echo str_repeat('=', 100) . "\n";
foreach ($divergences as $d) {
    printf("[%-4s] %-46s %-14s %s\n", $d['kind'], substr($d['case'], 0, 46), $d['tool'], $d['detail']);
}
printf("\n%d divergence(s) across %d case(s).\n", count($divergences), $processed);
if ($unmapped !== []) {
    echo "\nUnmapped codes (add to categories.json):\n";
    arsort($unmapped);
    foreach ($unmapped as $code => $n) { printf("  %-40s x%d\n", $code, $n); }
}
