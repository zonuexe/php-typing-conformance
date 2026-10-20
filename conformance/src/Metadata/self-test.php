<?php

declare(strict_types=1);

/**
 * Self-test for ReleaseTable::rewrite, the in-place update of data/releases.toml.
 *
 * Run: php conformance/src/Metadata/self-test.php
 */

use Conformance\Metadata\Release;
use Conformance\Metadata\ReleaseTable;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$failures = 0;

function expect(string $label, mixed $actual, mixed $expected): void
{
    global $failures;
    if ($actual !== $expected) {
        $failures++;
        fwrite(STDERR, sprintf(
            "FAIL %s\n  expected %s\n  actual   %s\n",
            $label,
            var_export($expected, true),
            var_export($actual, true),
        ));
        return;
    }

    echo "ok  {$label}\n";
}

$table = <<<'TOML'
    # Header prose.

    # Analyzers.

    [phan]
    version = "6.0.10"
    date = "2026-10-12"

    # Reference-only: listed, not measured.

    [symfony-lsp]
    version = "0.23.0" # trailing note
    date = "2026-09-26"

    TOML;

// Only the values move; every comment, blank line and table stays put.
expect('rewrite/values-only', ReleaseTable::rewrite($table, [
    'phan' => new Release('6.0.11', '2026-10-15'),
    'symfony-lsp' => new Release('0.24.0', '2026-10-17'),
]), <<<'TOML'
    # Header prose.

    # Analyzers.

    [phan]
    version = "6.0.11"
    date = "2026-10-15"

    # Reference-only: listed, not measured.

    [symfony-lsp]
    version = "0.24.0" # trailing note
    date = "2026-10-17"

    TOML);

// Unchanged releases leave the file byte for byte.
expect('rewrite/no-op', ReleaseTable::rewrite($table, [
    'phan' => new Release('6.0.10', '2026-10-12'),
]), $table);

// A tool without a table is refused, not appended somewhere arbitrary.
try {
    ReleaseTable::rewrite($table, ['new-tool' => new Release('1.0.0', '2026-10-20')]);
    expect('rewrite/missing-entry', 'no exception', 'exception');
} catch (RuntimeException $e) {
    expect('rewrite/missing-entry', str_contains($e->getMessage(), 'new-tool'), true);
}

// The committed table survives a no-op rewrite intact.
$path = dirname(__DIR__, 2) . '/data/releases.toml';
$committed = (string) file_get_contents($path);
expect('rewrite/committed-table', ReleaseTable::rewrite($committed, ReleaseTable::fromTomlFile($path)), $committed);

if ($failures > 0) {
    fwrite(STDERR, sprintf("%d failure(s)\n", $failures));
    exit(1);
}
