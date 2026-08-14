<?php

declare(strict_types=1);

/**
 * Append `// V` to unmarked valid-control lines in `// T` tests.
 *
 * Only touches files whose names start with the given prefix (default:
 * phpdoc_advanced_fallback_). Dry-run unless --apply is passed.
 *
 *   php conformance/src/apply-valid-controls.php [--apply] [prefix]
 */

use Conformance\Expectation\ExpectationParser;

require_once __DIR__ . '/../vendor/autoload.php';

$apply = in_array('--apply', $argv, true);
$prefix = 'phpdoc_advanced_fallback_';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        $prefix = $arg;
    }
}

$parser = new ExpectationParser();
$testsDir = dirname(__DIR__) . '/tests';
$changed = 0;

foreach (scandir($testsDir) ?: [] as $entry) {
    if (!str_ends_with($entry, '.php') || str_starts_with($entry, '_') || !str_starts_with($entry, $prefix)) {
        continue;
    }

    $path = $testsDir . '/' . $entry;
    $markers = $parser->parseTypeMarkers($path);
    if ($markers === []) {
        continue;
    }

    $expected = $parser->parseFile($path);
    $claimed = [];
    foreach ($expected as $d) {
        $claimed[$d->line] = true;
    }
    foreach ($markers as $marker) {
        $claimed[$marker->line] = true;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }

    $edits = [];
    foreach ($lines as $index => $line) {
        $lineNo = $index + 1;
        if (isset($claimed[$lineNo])) {
            continue;
        }

        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '//') || str_starts_with($trim, '*')
            || str_starts_with($trim, '/*') || str_starts_with($trim, '<?')
            || str_starts_with($trim, 'namespace ') || str_starts_with($trim, 'use ')
            || str_starts_with($trim, 'function ') || str_starts_with($trim, 'class ')
            || str_starts_with($trim, 'interface ') || str_starts_with($trim, 'trait ')
            || str_starts_with($trim, 'enum ') || str_starts_with($trim, 'final ')
            || str_starts_with($trim, 'public ') || str_starts_with($trim, 'protected ')
            || str_starts_with($trim, 'private ') || str_starts_with($trim, 'declare(')
            || str_starts_with($trim, 'foreach ') || str_starts_with($trim, 'foreach(')
            || str_starts_with($trim, '{') || str_starts_with($trim, '}')
            || str_starts_with($trim, 'if ') || str_starts_with($trim, 'if(')
            || str_starts_with($trim, 'throw ') || str_starts_with($trim, 'echo ')
            || str_starts_with($trim, '/**') || $trim === '*/'
            || str_contains($line, '// V') || str_contains($line, '// E') || str_contains($line, '// Q')
            || str_contains($line, '// T')
        ) {
            continue;
        }

        $isReturn = preg_match('/^return\s+\S/', $trim) === 1;
        $isCall = preg_match('/^(?:\\\\)?[A-Za-z_][\w\\\\]*\s*\(.*\)\s*;$/', $trim) === 1
            && preg_match('/^(?:\\\\)?(declare|assert|fopen|fclose)\b/i', $trim) !== 1;

        if (!$isReturn && !$isCall) {
            continue;
        }

        // A `return` is a valid control only when it implements a `// T`
        // spelling (`@return number`, …), not a helper like `impureFunction`.
        if ($isReturn) {
            $inTypeFunction = false;
            for ($back = $index; $back >= 0; $back--) {
                if (preg_match('/^\s*(?:final\s+)?function\s+/', $lines[$back]) === 1) {
                    $inTypeFunction = isset($claimed[$back + 1]);
                    break;
                }
            }
            if (!$inTypeFunction) {
                continue;
            }
        }

        $edits[$index] = rtrim($line) . ' // V';
    }

    if ($edits === []) {
        continue;
    }

    echo $entry . "\n";
    foreach ($edits as $index => $newLine) {
        echo sprintf("  %d: %s\n", $index + 1, $newLine);
    }

    if ($apply) {
        foreach ($edits as $index => $newLine) {
            $lines[$index] = $newLine;
        }
        file_put_contents($path, implode("\n", $lines) . "\n");
    }

    $changed++;
}

printf("\n%d file(s) %s\n", $changed, $apply ? 'updated' : 'would change (pass --apply)');
