<?php

declare(strict_types=1);

/**
 * Measure PhpStorm against the hover and navigation probes the language
 * servers get, through the MCP server built into the IDE, and write
 * results/lsp/phpstorm.toml next to the language servers' results.
 *
 * Usage:
 *   PHPSTORM=/path/to/phpstorm php src/run-phpstorm-probes.php
 *
 * PHPSTORM is the IDE's launcher (default `phpstorm`). The installation needs
 * a licence and must not be running: each session launches it headless, on a
 * scratch project under <temp>/phpstorm-probes/, and stops it afterwards.
 *
 * Separate from run-lsp-probes.php because PhpStorm is not a language server:
 * the same probe definitions, fixtures, corpus and grading, but a different
 * transport. Only the layers an MCP tool answers faithfully are asked. Hover
 * is Quick Documentation, and definition and references come from what
 * get_symbol_info resolves. The capability matrix and the framework probes are
 * left out: MCP has no request for completion, signature help and the like,
 * and does not resolve Laravel Idea's keys, so their cells would read as
 * missing features the IDE does have. PhpStormProbeRunner explains the mapping.
 */

use Conformance\Lsp\LspResultFile;
use Conformance\Lsp\NavigationDefinitions;
use Conformance\Lsp\PhpStormProbeRunner;
use Conformance\Lsp\ProbeDefinitions;
use Conformance\Lsp\ProbeGrading;
use Conformance\Result\ResultsUpdate;

require_once __DIR__ . '/../vendor/autoload.php';

$rootDir = dirname(__DIR__);
$lspDir = $rootDir . '/lsp';
$resultsRoot = $rootDir . '/results';

$launcher = (($value = getenv('PHPSTORM')) !== false && $value !== '') ? $value : 'phpstorm';

$definitions = ProbeDefinitions::load($lspDir . '/probes.toml', $lspDir . '/fixtures');
$stripGrading = static fn (array $probe): array => array_diff_key(
    $probe,
    array_flip(['feature', 'expected', 'precise', 'reject']),
);
$capabilityProbes = array_map($stripGrading, $definitions->capabilityProbes);
$hoverProbes = array_map($stripGrading, $definitions->hoverProbes);

$runner = new PhpStormProbeRunner($launcher, sys_get_temp_dir() . '/phpstorm-probes');
$grading = new ProbeGrading();

// ---- The fixture workspace: hover ----

echo "== phpstorm: fixtures\n";
// The definition probe's reference resolves only once the fixtures are indexed.
$definitionProbe = array_values(array_filter($capabilityProbes, static fn (array $probe): bool => $probe['id'] === 'definition'))[0];
$fixtures = $runner->open('fixtures', $lspDir . '/fixtures', ready: [
    'file' => (string) $definitionProbe['file'],
    'line' => (int) $definitionProbe['line'],
    'character' => (int) $definitionProbe['character'],
]);
$hoverOutput = $runner->run($fixtures, $hoverProbes);

$serverInfo = $runner->serverInfo();
$payload = [
    'tool' => 'phpstorm',
    'version' => trim('PhpStorm ' . $serverInfo['version']),
    'server_info' => trim($serverInfo['name'] . ' ' . $serverInfo['version']),
    'interface' => 'mcp',
    'dynamic_registrations' => [],
    // tools/list is this interface's handshake, kept as the record of what
    // it could be asked.
    'raw_capabilities' => (string) json_encode(['tools' => $runner->toolNames()], JSON_UNESCAPED_SLASHES),
    'hover' => $grading->hoverConformance($hoverOutput, $definitions->hoverProbes),
];

// ---- The psysh corpus: navigation ----

$navigation = NavigationDefinitions::tryLoad($lspDir . '/navigation.toml');
if ($navigation === null) {
    echo "note: navigation corpus not found; skipping the real-project layer\n";
} else {
    echo "== phpstorm: psysh\n";
    $navigationProbes = [];
    foreach ($navigation->symbols as $symbol) {
        if ($symbol['probe'] !== null) {
            $navigationProbes[] = ['id' => 'nav-def:' . $symbol['id'], 'method' => 'textDocument/definition', ...$symbol['probe']];
        }
        $navigationProbes[] = ['id' => 'nav-refs:' . $symbol['id'], 'method' => 'textDocument/references', ...$symbol['decl']];
    }

    $firstProbe = array_values(array_filter(array_column($navigation->symbols, 'probe')))[0];
    $psysh = $runner->open('psysh', $navigation->root, ready: $firstProbe);

    $payload['navigation_corpus'] = "{$navigation->project}@" . substr($navigation->commit, 0, 12);
    $payload['navigation'] = $grading->navigation($runner->run($psysh, $navigationProbes), $navigation->symbols);
}

$runner->close();

$path = $resultsRoot . '/lsp/phpstorm.toml';
if (file_put_contents($path, LspResultFile::encode($payload)) === false) {
    fwrite(STDERR, "Cannot write {$path}\n");
    exit(1);
}
echo "   {$path}\n";

// The same stamp run-lsp-probes.php and main.php write.
$resultsUpdate = new ResultsUpdate($resultsRoot, $rootDir . '/tests');
$previousUpdate = $resultsUpdate->recorded();
$currentUpdate = $resultsUpdate->record();
printf(
    $currentUpdate === $previousUpdate
        ? "Nothing changed; the update stamp stays at %s\n"
        : "Recorded the update at %s\n",
    $currentUpdate,
);
