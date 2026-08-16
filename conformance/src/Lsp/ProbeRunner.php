<?php

declare(strict_types=1);

namespace Conformance\Lsp;

use RuntimeException;

/**
 * Runs one language server through lsp-probe.mjs and returns its raw output.
 *
 * The runner owns the filesystem half of a probe run: a private temp
 * workspace holding a copy of every fixture plus the server's own config
 * files, and the spec JSON handed to the Node client. The client owns the
 * protocol half and nothing else. Workspaces are deleted afterwards even on
 * failure, so a crashed server cannot leave state that skews the next run.
 */
final class ProbeRunner
{
    public function __construct(
        private readonly string $nodeBinary,
        private readonly string $clientPath,
        private readonly string $fixturesDir,
    ) {
    }

    /**
     * Every fixture is always copied into the workspace; $open picks which
     * of them the session opens. The distinction exists because opening a
     * file is not neutral: Psalm stops answering variable-level hover in a
     * file once another file is opened in the same session, so the hover
     * conformance run opens its one fixture and nothing else.
     *
     * @param list<array<string, mixed>> $probes
     * @param list<string>|null $open fixture basenames to didOpen; null opens all
     * @param string|null $sourceDir a directory to copy recursively as the
     *        workspace instead of the flat fixture files — the real-project
     *        corpus for the navigation probes, or the Gate Laravel app
     * @param array<string, string>|null $configFiles overrides the server's
     *        own configFiles; a corpus needs different psalm/phan configs
     *        than the fixtures do
     * @param array<string, mixed> $specOverrides extra spec fields for the
     *        client (indexTimeoutMs etc.) — a real project indexes slower
     *        than five fixture files
     * @param bool $linkVendor when copying a Laravel app, skip vendor/ and
     *        symlink the source tree's vendor so artisan tinker works
     *        without duplicating hundreds of megabytes per run
     * @return array<string, mixed> the client's JSON output, decoded
     */
    public function run(
        LspServer $server,
        array $probes,
        ?array $open = null,
        ?string $sourceDir = null,
        ?array $configFiles = null,
        array $specOverrides = [],
        bool $linkVendor = false,
    ): array {
        $workspace = $this->makeTempDir('lsp-ws-');
        $specFile = null;

        try {
            if ($sourceDir !== null) {
                $this->copyCorpus($sourceDir, $workspace, $linkVendor);
                $open ??= [];
            } else {
                $available = [];
                foreach (glob($this->fixturesDir . '/*.php') ?: [] as $fixture) {
                    $name = basename($fixture);
                    copy($fixture, $workspace . '/' . $name);
                    $available[] = $name;
                }
                $open ??= $available;
            }
            foreach ($configFiles ?? $server->configFiles as $relative => $source) {
                $destination = $workspace . '/' . $relative;
                $directory = dirname($destination);
                if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                    throw new RuntimeException("Cannot create {$directory}");
                }
                copy($source, $destination);
            }
            // openExtra is for the fixture session (Laravel's helpers.php).
            // A corpus workspace that did not copy that file must not try
            // to didOpen it — the client reads every path in `open`.
            foreach ($server->openExtra as $extra) {
                if (!in_array($extra, $open, true) && is_file($workspace . '/' . $extra)) {
                    $open[] = $extra;
                }
            }
            // lib.php must be open before the files that reference it, and
            // alphabetical order already guarantees that; sort to make the
            // guarantee explicit rather than lucky.
            sort($open);

            $spec = [
                'command' => $server->commandFor($workspace),
                'workspace' => $workspace,
                'open' => $open,
                'probes' => $probes,
                ...$specOverrides,
            ];
            if ($server->settings !== null) {
                $spec['settings'] = $server->settings;
            }
            $initializationOptions = $server->initializationOptions;
            if ($server->tool === 'intelephense') {
                $initializationOptions['storagePath'] = $this->makeTempDir('lsp-store-');
            }
            if ($initializationOptions !== []) {
                $spec['initializationOptions'] = $initializationOptions;
            }

            $specFile = $workspace . '/.probe-spec.json';
            file_put_contents($specFile, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $command = sprintf(
                '%s %s %s',
                escapeshellarg($this->nodeBinary),
                escapeshellarg($this->clientPath),
                escapeshellarg($specFile),
            );
            exec($command . ' 2>/dev/null', $output, $exitCode);
            $json = implode("\n", $output);

            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                throw new RuntimeException(
                    "lsp-probe for {$server->tool} produced no JSON (exit {$exitCode}): " . substr($json, 0, 500),
                );
            }

            return $decoded;
        } finally {
            if ($specFile !== null && file_exists($specFile)) {
                @unlink($specFile);
            }
            $this->removeDir($workspace);
        }
    }

    private function copyCorpus(string $source, string $destination, bool $linkVendor): void
    {
        $exclude = $linkVendor
            ? '--exclude .git --exclude vendor --exclude node_modules'
            : '--exclude .git';
        $command = sprintf(
            'rsync -a %s %s %s',
            $exclude,
            escapeshellarg($source . '/'),
            escapeshellarg($destination . '/'),
        );
        exec($command . ' 2>/dev/null', $ignored, $copyExit);
        if ($copyExit !== 0) {
            throw new RuntimeException("Failed to copy corpus {$source} into {$destination}");
        }

        if ($linkVendor && is_dir($source . '/vendor')) {
            if (!@symlink($source . '/vendor', $destination . '/vendor')) {
                throw new RuntimeException("Failed to link vendor from {$source} into {$destination}");
            }
        }
    }

    private function makeTempDir(string $prefix): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid($prefix, true);
        if (!mkdir($path, 0777, true)) {
            throw new RuntimeException("Cannot create temp dir {$path}");
        }

        // Resolved, not as returned: on macOS the temp dir lives behind the
        // /var -> /private/var symlink, and a server that realpaths its files
        // (Phan does) would otherwise publish under file:///private/var/...
        // while every URI this side of the protocol says file:///var/... —
        // nothing matches and every probe comes back empty.
        $resolved = realpath($path);
        if ($resolved === false) {
            throw new RuntimeException("Cannot resolve temp dir {$path}");
        }

        return $resolved;
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $entries = scandir($path) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            is_dir($child) && !is_link($child) ? $this->removeDir($child) : @unlink($child);
        }
        @rmdir($path);
    }
}
