<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for phpy (https://github.com/DEVSENSE/phpy), DEVSENSE's PHP Tools
 * analysis engine packaged as a standalone Node CLI.
 *
 * phpy indexes everything under its root directory (the current working
 * directory by default) rather than restricting itself to the paths given on
 * the command line -- run from an unrelated cwd it will happily walk that
 * whole tree and report on files never passed to it. The run therefore happens
 * in a private temp workspace, with that workspace as the invoked process's
 * cwd, mirroring IntelephenseChecker's private-workspace approach for the same
 * reason.
 *
 * That workspace holds the whole corpus and is checked in one invocation, the
 * way {@see QodanaChecker} reads one report. phpy indexed the directory on
 * every per-test invocation anyway, so the corpus was being walked 218 times
 * to report on one file at a time: ~1.4 seconds per test, ~306 for the column,
 * against 1.6 seconds for a single `--check .` over all of it. A per-file diff
 * against the stored results confirmed the shared workspace changes no
 * diagnostic -- every test file carries its own namespace, so nothing resolves
 * across them that did not resolve before.
 *
 * phpy's diagnostics carry no code, only a message, so filtering relies on
 * matching message text against two patterns, both confirmed against every
 * test's actual output before being added here (grep across
 * results/phpy/*.toml, not a guess):
 * - "$x is assigned, but its value is never used" fires on nearly every test
 *   here -- these fixtures exist to exercise a type signature, not to consume
 *   every parameter -- the same rationale IntelephenseChecker documents for
 *   dropping its P1003.
 * - "Special function 'x' should be called in global namespace to allow
 *   compiler optimization" is a PHP Tools performance hint, unrelated to
 *   types; every occurrence found was the sole diagnostic turning an
 *   otherwise-passing test into a false "Fail" via an unrelated unexpected
 *   error.
 */
final class PhpyChecker implements Checker
{
    /** Matches phpy's non-type-conformance noise; see class docblock. */
    private const IGNORED_MESSAGE_PATTERN = '/is assigned, but its value is never used$|should be called in global namespace to allow compiler optimization\.$/';

    /**
     * The whole corpus, keyed by file name. Built on the first analysis.
     *
     * @var array<string, array<int, list<string>>>|null
     */
    private ?array $report = null;

    public function __construct(
        private readonly string $nodeBinary,
        private readonly string $cliPath,
        private readonly string $testsDir,
    ) {
    }

    public function name(): string
    {
        return 'phpy';
    }

    public function version(): string
    {
        $command = sprintf(
            '%s %s --version 2>/dev/null',
            escapeshellarg($this->nodeBinary),
            escapeshellarg($this->cliPath),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine phpy version.');
        }

        return 'phpy ' . trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        return $this->report()[$testCase->fileName] ?? [];
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    private function report(): array
    {
        if ($this->report !== null) {
            return $this->report;
        }

        $workspace = sys_get_temp_dir() . '/phpy-conformance-' . bin2hex(random_bytes(8));
        if (!mkdir($workspace, 0o700, true) && !is_dir($workspace)) {
            throw new RuntimeException('Failed to create the phpy workspace.');
        }

        try {
            foreach (glob($this->testsDir . '/*.php') ?: [] as $sourcePath) {
                if (!copy($sourcePath, $workspace . '/' . basename($sourcePath))) {
                    throw new RuntimeException(sprintf('Failed to copy %s into the phpy workspace', $sourcePath));
                }
            }

            $command = sprintf(
                'cd %s && %s %s --check . 2>/dev/null',
                escapeshellarg($workspace),
                escapeshellarg($this->nodeBinary),
                escapeshellarg($this->cliPath),
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException('phpy invocation failed for the test corpus.');
            }

            return $this->report = $this->parseOutput(implode("\n", $output));
        } finally {
            $this->removeDirectory($workspace);
        }
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    private function parseOutput(string $output): array
    {
        $report = [];

        foreach (explode("\n", $this->stripAnsi($output)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Format: `file://<absolute-path>(<line>, <col>): <message>`
            if (!preg_match(
                '/^file:\/\/(?<path>.+)\((?<line>\d+),\s*(?<col>\d+)\):\s*(?<message>.+)$/',
                $line,
                $matches,
            )) {
                continue;
            }

            // phpy resolves symlinks in the paths it reports, so the temp
            // workspace path it prints may not equal the one we passed in
            // byte-for-byte; key on the basename instead, as PzoomChecker does.
            $fileName = basename($matches['path']);

            $message = trim($matches['message']);
            if ($message === '' || preg_match(self::IGNORED_MESSAGE_PATTERN, $message) === 1) {
                continue;
            }

            $lineNumber = (int) $matches['line'];
            if ($lineNumber <= 0) {
                continue;
            }

            $report[$fileName][$lineNumber] ??= [];
            $report[$fileName][$lineNumber][] = $message;
        }

        foreach ($report as &$diagnostics) {
            ksort($diagnostics);
        }

        return $report;
    }

    private function stripAnsi(string $text): string
    {
        return (string) preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $text);
    }

    private function removeDirectory(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
