<?php

declare(strict_types=1);

/**
 * Print what the expectation parser actually sees in a test file.
 *
 * Reading a test and believing you know which lines are probes is how a
 * missing `// T` survives review: the marker covers the docblock directly
 * above it and only that one, and a docblock nobody claimed collects
 * diagnostics as unexpected errors. Ask the parser instead.
 *
 * Usage: php .claude/skills/add-conformance-test/scripts/dump-markers.php <test.php>
 */

use Conformance\Expectation\ExpectationParser;

$root = dirname(__DIR__, 4);
require_once $root . '/conformance/vendor/autoload.php';

$path = $argv[1] ?? null;
if ($path === null || !is_file($path)) {
    fwrite(STDERR, "usage: dump-markers.php <conformance/tests/name.php>\n");
    exit(2);
}

$parser = new ExpectationParser();
$lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

$kinds = [];
foreach ($parser->parseFile($path) as $diagnostic) {
    $kinds[$diagnostic->line][] = match (true) {
        $diagnostic->valid => 'V',
        $diagnostic->quiet => $diagnostic->required ? 'Q' : 'Q?',
        default => $diagnostic->required ? 'E' : 'E?',
    } . ($diagnostic->tag !== null ? '[' . $diagnostic->tag . ']' : '');
}

$markers = [];
foreach ($parser->parseTypeMarkers($path) as $marker) {
    $markers[$marker->line] = $marker->spelling;
}

$interesting = $kinds + $markers;
ksort($interesting);

foreach (array_keys($interesting) as $line) {
    printf(
        "%4d  %-10s %-18s %s\n",
        $line,
        implode(' ', $kinds[$line] ?? []),
        isset($markers[$line]) ? 'T: ' . $markers[$line] : '',
        trim($lines[$line - 1] ?? ''),
    );
}

// Every annotated declaration needs a `// T` beneath it, so a docblock the
// markers do not reach is the next false Fail waiting to happen.
$unclaimed = [];
foreach ($lines as $index => $line) {
    $trimmed = trim($line);
    if (str_starts_with($trimmed, '/**') || str_starts_with($trimmed, '*')) {
        if (!isset($markers[$index + 1]) && str_contains($trimmed, '@')) {
            $unclaimed[] = $index + 1;
        }
    }
}

if ($unclaimed !== []) {
    printf("\nDocblock lines with a tag and no `// T` cover: %s\n", implode(', ', $unclaimed));
    echo "Fine for a declaration no tool will blame; check the ones carrying the spelling under test.\n";
}
