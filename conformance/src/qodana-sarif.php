<?php

declare(strict_types=1);

/**
 * Extract conformance diagnostics from a PhpStorm/Qodana SARIF report.
 *
 * Usage:
 *   php conformance/src/qodana-sarif.php [qodana.sarif.json] [options]
 *
 * With no path, the newest qodana_output*\/qodana.sarif.json under the
 * temporary directory is used — that is where PhpStorm leaves each run.
 *
 * Options:
 *   --format=json|summary|rules   json (default) emits path => line => messages
 *   --rules=type|all              inspection allowlist; defaults to the typing set
 *   --prefix=PATH                 repository-relative path prefix (default conformance/tests/)
 *   --search=DIR                  where to look for qodana_output* (default: system temp)
 *   --promo                       also include the Ultimate-only "promo" hits (unstable)
 */

use Conformance\Checker\QodanaSarifReport;

require_once __DIR__ . '/../vendor/autoload.php';

$argvValues = $argv ?? [];
$reportPath = null;
$format = 'json';
$rules = 'type';
$prefix = 'conformance/tests/';
$searchDirectory = null;
$includePromo = false;

foreach (array_slice($argvValues, 1) as $arg) {
    if (str_starts_with($arg, '--format=')) {
        $format = substr($arg, strlen('--format='));
    } elseif (str_starts_with($arg, '--rules=')) {
        $rules = substr($arg, strlen('--rules='));
    } elseif (str_starts_with($arg, '--prefix=')) {
        $prefix = substr($arg, strlen('--prefix='));
    } elseif (str_starts_with($arg, '--search=')) {
        $searchDirectory = substr($arg, strlen('--search='));
    } elseif ($arg === '--promo') {
        $includePromo = true;
    } elseif (str_starts_with($arg, '-')) {
        fwrite(STDERR, sprintf("Unknown option %s\n", $arg));
        exit(2);
    } else {
        $reportPath = $arg;
    }
}


if (!in_array($format, ['json', 'summary', 'rules'], true)) {
    fwrite(STDERR, sprintf("Unknown --format=%s\n", $format));
    exit(2);
}

if (!in_array($rules, ['type', 'all'], true)) {
    fwrite(STDERR, sprintf("Unknown --rules=%s\n", $rules));
    exit(2);
}

try {
    if ($reportPath === null) {
        $reportPath = QodanaSarifReport::locateLatest($searchDirectory);
        fwrite(STDERR, sprintf("Using %s\n", $reportPath));
    } elseif (is_dir($reportPath)) {
        $reportPath = rtrim($reportPath, '/') . '/qodana.sarif.json';
    }

    $report = QodanaSarifReport::fromFile(
        path: $reportPath,
        pathPrefix: $prefix,
        ruleFilter: $rules === 'all' ? null : QodanaSarifReport::TYPE_RULES,
        includePromo: $includePromo,
    );
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

// Messages are copied verbatim into the result TOML, so a localised IDE would
// make the recorded output depend on whoever ran the inspection.
if ($report->localised) {
    fwrite(STDERR, "warning: report is localised; re-run with the IDE UI language set to English so messages stay comparable.\n");
}

// A SARIF file is a snapshot of a working tree that PhpStorm saw at some point.
// Warn rather than fail: re-analysing is the user's call, not this script's.
$head = trim((string) shell_exec('git -C ' . escapeshellarg(dirname(__DIR__, 2)) . ' rev-parse HEAD 2>/dev/null'));
if ($report->revisionId !== null && $head !== '' && $report->revisionId !== $head) {
    fwrite(STDERR, sprintf(
        "warning: report was produced at %s but HEAD is %s; re-run the inspection to re-measure.\n",
        substr($report->revisionId, 0, 12),
        substr($head, 0, 12),
    ));
}

if ($format === 'rules') {
    foreach ($report->ruleTitles as $id => $title) {
        printf("%s\t%s\n", $id, $title);
    }
    exit(0);
}

if ($format === 'summary') {
    printf(
        "%s %s (%s, %s)\n",
        $report->toolName,
        $report->toolVersion,
        $report->startedAt ?? 'unknown time',
        $report->localised ? 'localised' : 'English',
    );
    printf("%d file(s), %d diagnostic(s)\n\n", count($report->diagnostics), array_sum(array_map(
        static fn (array $lines): int => array_sum(array_map('count', $lines)),
        $report->diagnostics,
    )));

    foreach ($report->diagnostics as $uri => $lines) {
        foreach ($lines as $line => $messages) {
            foreach ($messages as $message) {
                printf("%s:%d\t%s\n", $uri, $line, $message);
            }
        }
    }

    exit(0);
}

echo json_encode([
    'tool' => [
        'name' => $report->toolName,
        'version' => $report->toolVersion,
        'revision' => $report->revisionId,
        'startedAt' => $report->startedAt,
        'localised' => $report->localised,
        'rules' => $rules,
        'promo' => $includePromo,
    ],
    'diagnostics' => $report->diagnostics,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
