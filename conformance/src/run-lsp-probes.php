<?php

declare(strict_types=1);

/**
 * Measure the language servers: launch each one headless, record what its
 * initialize handshake advertises, exercise the probes from lsp/probes.toml,
 * and write one TOML per server under results/lsp/.
 *
 * Usage:
 *   php src/run-lsp-probes.php               # all launchable servers
 *   php src/run-lsp-probes.php --tool=psalm  # one server, repeatable
 *
 * Deliberately separate from main.php: the diagnostics matrix and the
 * capability matrix move for different reasons — a new tool release changes
 * both, but a new probe or fixture only changes this one, and re-running
 * eleven analyzers to remeasure four language servers helps nobody.
 *
 * It shares one thing with main.php regardless: results/updated.toml. The
 * "Results last updated" line on the report reads as a claim about the whole
 * page, language-server section included, so this run stamps it exactly as
 * main.php does. The digest ResultsUpdate hashes already covers results/lsp/
 * — its glob (one directory, then any .toml file) matches a per-server file
 * here exactly as it matches a per-test one under results/<analyzer>/ — so
 * only the write needed adding, not the accounting.
 */

use Conformance\Lsp\LaravelCorpus;
use Conformance\Lsp\LspResultFile;
use Conformance\Lsp\LspServerCatalog;
use Conformance\Lsp\NavigationDefinitions;
use Conformance\Lsp\ProbeDefinitions;
use Conformance\Lsp\ProbeGrading;
use Conformance\Lsp\ProbeRunner;
use Conformance\Result\ResultsUpdate;

require_once __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);
$projectRoot = dirname($rootDir);
$lspDir = $rootDir . '/lsp';
$resultsRoot = $rootDir . '/results';
$resultsDir = $resultsRoot . '/lsp';

$only = [];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--tool=')) {
        $only[] = substr($arg, strlen('--tool='));
    }
}

$definitions = ProbeDefinitions::load($lspDir . '/probes.toml', $lspDir . '/fixtures');
// The client-facing entries must not carry grading fields; the client
// ignores unknown keys, but the spec file reads better without them.
$stripGrading = static fn (array $probe): array => array_diff_key(
    $probe,
    array_flip(['feature', 'expected', 'precise', 'reject']),
);
$capabilityProbes = array_map($stripGrading, $definitions->capabilityProbes);
$hoverProbes = array_map($stripGrading, $definitions->hoverProbes);
// Hover conformance runs as its own session that opens only its fixture:
// what a hover shows must not depend on which unrelated files happen to be
// open, and for Psalm it does (see ProbeRunner::run).
$hoverFixtures = array_values(array_unique(array_map(
    static fn (array $probe): string => (string) $probe['file'],
    $hoverProbes,
)));

// The real-project navigation session. Optional: on a machine without the
// survey checkout the layer is skipped and existing results stay committed.
$navigation = NavigationDefinitions::tryLoad($lspDir . '/navigation.toml');
$navigationProbes = [];
$navigationOpen = [];
if ($navigation !== null) {
    foreach ($navigation->symbols as $symbol) {
        if ($symbol['probe'] !== null) {
            $navigationProbes[] = [
                'id' => 'nav-def:' . $symbol['id'],
                'method' => 'textDocument/definition',
                ...$symbol['probe'],
            ];
        }
        $navigationProbes[] = [
            'id' => 'nav-refs:' . $symbol['id'],
            'method' => 'textDocument/references',
            ...$symbol['decl'],
        ];
        // Only the files probes are sent against get opened; the rest of the
        // reference set must be found through the server's own index, which
        // is the dependency-graph question this layer exists to ask.
        if ($symbol['probe'] !== null) {
            $navigationOpen[] = (string) $symbol['probe']['file'];
        }
        $navigationOpen[] = (string) $symbol['decl']['file'];
    }
    $navigationOpen = array_values(array_unique($navigationOpen));
} else {
    echo "note: navigation corpus not found; skipping the real-project layer\n";
}
$navigationConfigs = [
    'psalm' => ['psalm.xml' => $lspDir . '/config/navigation/psalm.xml'],
    'phan' => ['.phan/config.php' => $lspDir . '/config/navigation/phan/config.php'],
];

$laravelCorpus = LaravelCorpus::tryLoad($lspDir . '/laravel/corpus.toml', $projectRoot);
if ($laravelCorpus === null) {
    echo "note: Laravel corpus not found; framework probes use the stub artisan workspace\n";
} elseif (!$laravelCorpus->hasVendor) {
    echo "note: Laravel corpus has no vendor/; run `make install-laravel-corpus` for route/view/config probes\n";
}

$runner = new ProbeRunner(
    nodeBinary: 'node',
    clientPath: __DIR__ . '/Lsp/lsp-probe.mjs',
    fixturesDir: $lspDir . '/fixtures',
);
$grading = new ProbeGrading();

if (!is_dir($resultsDir) && !mkdir($resultsDir, 0777, true) && !is_dir($resultsDir)) {
    fwrite(STDERR, "Cannot create {$resultsDir}\n");
    exit(1);
}

$failures = 0;

foreach (LspServerCatalog::all($projectRoot, $lspDir) as $server) {
    if ($only !== [] && !in_array($server->tool, $only, true)) {
        continue;
    }

    echo "== {$server->tool}\n";
    $started = microtime(true);

    // A real project indexes slower than five fixture files, hence the
    // generous navigation windows. php-lsp is the exception: it goes
    // unresponsive on the corpus and every navigation probe times out, so
    // the default window costs 30s per symbol for a verdict that 5s reaches
    // just as well — shorten its per-probe and index windows only, and keep
    // the generous defaults for the servers that actually answer. Verified
    // against 0.24.1: a 30s window records the identical timeouts.
    $navigationTimeouts = match ($server->tool) {
        'php-lsp' => ['indexTimeoutMs' => 15000, 'timeoutMs' => 120000, 'probeTimeoutMs' => 5000],
        default => ['indexTimeoutMs' => 90000, 'timeoutMs' => 600000, 'probeTimeoutMs' => 30000],
    };

    $frameworkDefs = [];
    $frameworkRequests = [];
    $corpusFrameworkDefs = [];
    $corpusFrameworkRequests = [];
    if ($server->frameworkProbesFile !== null) {
        $frameworkDefs = ProbeDefinitions::loadFramework(
            $server->frameworkProbesFile,
            dirname($server->frameworkProbesFile),
        );
        $frameworkRequests = array_values(array_filter(
            $frameworkDefs,
            static fn (array $probe): bool => ($probe['method'] ?? '') !== 'push-diagnostics',
        ));
    }
    $corpusProbesFile = $lspDir . '/laravel/corpus-probes.toml';
    if ($server->tool === 'laravel-lsp' && $laravelCorpus !== null && is_file($corpusProbesFile)) {
        $corpusFrameworkDefs = ProbeDefinitions::loadFramework($corpusProbesFile, $laravelCorpus->root);
        $corpusFrameworkRequests = array_values(array_filter(
            $corpusFrameworkDefs,
            static fn (array $probe): bool => ($probe['method'] ?? '') !== 'push-diagnostics',
        ));
    }

    try {
        $capabilitySessionProbes = $capabilityProbes;
        if ($corpusFrameworkDefs === []) {
            $capabilitySessionProbes = [...$capabilityProbes, ...$frameworkRequests];
        }
        $output = $runner->run($server, $capabilitySessionProbes);
        $hoverOutput = $runner->run($server, $hoverProbes, $hoverFixtures);
        $frameworkOutput = null;
        if ($corpusFrameworkDefs !== [] && $laravelCorpus !== null) {
            $open = array_values(array_unique(array_map(
                static fn (array $probe): string => (string) $probe['file'],
                $corpusFrameworkDefs,
            )));
            $frameworkOutput = $runner->run(
                $server,
                $corpusFrameworkRequests,
                $open,
                sourceDir: $laravelCorpus->root,
                configFiles: [
                    '.env' => $lspDir . '/laravel/gate.env',
                ],
                specOverrides: ['indexTimeoutMs' => 90000, 'timeoutMs' => 180000, 'probeTimeoutMs' => 20000],
                linkVendor: true,
            );
        }
        $navigationOutput = ($navigation === null || $server->skipNavigation) ? null : $runner->run(
            $server,
            $navigationProbes,
            $navigationOpen,
            sourceDir: $navigation->root,
            configFiles: $navigationConfigs[$server->tool] ?? [],
            specOverrides: $navigationTimeouts,
        );
    } catch (Throwable $e) {
        fwrite(STDERR, "{$server->tool}: {$e->getMessage()}\n");
        $failures++;
        continue;
    }

    // The fixture sessions are load-bearing for the whole file, but a
    // navigation session that died still leaves the layers that succeeded,
    // plus its own failure on the record: the client prints whatever probes
    // it completed before failing, so a partial navigation session grades
    // partially rather than vanishing.
    $failure = $output['failure'] ?? $hoverOutput['failure'] ?? null;
    if ($failure !== null) {
        fwrite(STDERR, "{$server->tool}: client failure: {$failure}\n");
        $failures++;
        continue;
    }
    if (isset($frameworkOutput['failure'])) {
        fwrite(STDERR, "{$server->tool}: framework corpus session: {$frameworkOutput['failure']} (recorded, continuing)\n");
    }
    if (isset($navigationOutput['failure'])) {
        fwrite(STDERR, "{$server->tool}: navigation session: {$navigationOutput['failure']} (recorded, continuing)\n");
    }

    $serverInfo = $output['serverInfo'] ?? null;
    $payload = [
        'tool' => $server->tool,
        'version' => $server->version(),
        'server_info' => is_array($serverInfo)
            ? trim((string) ($serverInfo['name'] ?? '') . ' ' . (string) ($serverInfo['version'] ?? ''))
            : '',
        'dynamic_registrations' => $output['dynamicRegistrations'] ?? [],
        // The handshake verbatim, so the graded rows can always be re-audited
        // against what the server actually said.
        'raw_capabilities' => (string) json_encode($output['capabilities'] ?? [], JSON_UNESCAPED_SLASHES),
        'capabilities' => $grading->capabilities($output),
        'hover' => $grading->hoverConformance($hoverOutput, $definitions->hoverProbes),
    ];
    if ($corpusFrameworkDefs !== [] && $frameworkOutput !== null) {
        $payload['framework'] = $grading->framework($frameworkOutput, $corpusFrameworkDefs);
    } elseif ($frameworkDefs !== []) {
        $payload['framework'] = $grading->framework($output, $frameworkDefs);
    }
    if ($laravelCorpus !== null && $server->tool === 'laravel-lsp') {
        $payload['framework_corpus'] = "{$laravelCorpus->project}@" . substr($laravelCorpus->commit, 0, 12);
        if (isset($frameworkOutput['failure'])) {
            $payload['framework_failure'] = (string) $frameworkOutput['failure'];
        }
    }
    if ($navigation !== null && $navigationOutput !== null) {
        $payload['navigation_corpus'] = "{$navigation->project}@" . substr($navigation->commit, 0, 12);
        if (isset($navigationOutput['failure'])) {
            $payload['navigation_failure'] = (string) $navigationOutput['failure'];
        }
        $payload['navigation'] = $grading->navigation($navigationOutput, $navigation->symbols);
    }

    $path = "{$resultsDir}/{$server->tool}.toml";
    if (file_put_contents($path, LspResultFile::encode($payload)) === false) {
        fwrite(STDERR, "Cannot write {$path}\n");
        $failures++;
        continue;
    }

    printf("   %s  (%.1fs)\n", $path, microtime(true) - $started);
}

// The same stamp main.php writes: one "Results last updated" line covers the
// whole report, and the digest it hashes (results/*/*.toml, which matches
// results/lsp/<tool>.toml exactly like it matches results/<analyzer>/<test>.toml)
// already accounts for these files. Only the trigger was missing — without
// this call, an LSP-only run left the stamp exactly where a CLI-analyzer run
// last put it, silently understating how fresh the language-server section is.
$resultsUpdate = new ResultsUpdate($resultsRoot, $rootDir . '/tests');
$previousUpdate = $resultsUpdate->recorded();
$currentUpdate = $resultsUpdate->record();
printf(
    $currentUpdate === $previousUpdate
        ? "Nothing changed; the update stamp stays at %s\n"
        : "Recorded the update at %s\n",
    $currentUpdate,
);

exit($failures === 0 ? 0 : 1);
