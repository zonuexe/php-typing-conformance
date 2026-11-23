<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for Pun (https://github.com/shipfastlabs/pun), a Rust PHP toolchain
 * whose `pun analyse` subcommand ports PHPStan's rules onto Mago's parser and
 * analysis crates.
 *
 * CLI contract:
 *   pun analyse --no-agent --config <pun.toml> --format=json <paths...>
 *     -> stdout JSON: {"totals":{...},"files":{<path>:{"errors":N,"messages":[{"message","line","identifier",...}]}},"errors":[]}
 *     -> exit 0 = clean, exit 1 = findings reported, exit 2 = run/config error.
 *
 * `pun analyse` resolves `root()` from the current working directory and loads
 * `pun.toml` there (or via `--config`), so each invocation runs with the
 * conformance workspace as cwd and pins `conformance/pun.toml` (PHP 8.5,
 * `preset = "max"`, `bleeding-edge = true`).
 *
 * Invoked per test case (`[...$testCase->supportPaths, $testCase->path]`) rather
 * than in a single corpus batch: in Pun, if any file in an invocation hits a
 * `phpstan.parse` error, `pun analyse` discards all non-parse diagnostics from
 * every other file in that invocation. Per-test invocation isolates each test.
 *
 * `PUN_AGENT=0` and `--no-agent` suppress Pun's coding-agent detector, keeping
 * CLI behaviour identical whether the suite runs locally, in CI, or from an
 * agent harness. `PUN_BIN` overrides the binary path when set and non-empty.
 */
final class PunChecker implements Checker
{
    private readonly string $binaryPath;

    public function __construct(
        string $binaryPath,
        private readonly string $workspacePath,
        private readonly string $configPath,
    ) {
        $override = getenv('PUN_BIN');

        $this->binaryPath = is_string($override) && $override !== '' ? $override : $binaryPath;
    }

    public function name(): string
    {
        return 'pun';
    }

    public function version(): string
    {
        $command = sprintf(
            'PUN_AGENT=0 %s --version 2>&1',
            escapeshellarg($this->binaryPath),
        );
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine Pun version.');
        }

        return trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $paths = array_map(
            static fn (string $path): string => escapeshellarg(realpath($path) ?: $path),
            [...$testCase->supportPaths, $testCase->path],
        );

        $command = sprintf(
            'cd %s && PUN_AGENT=0 %s analyse --no-agent --config %s --format=json %s 2>&1',
            escapeshellarg($this->workspacePath),
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->configPath),
            implode(' ', $paths),
        );

        exec($command, $output, $exitCode);

        // 0 = clean, 1 = findings reported; 2 is a run/config failure.
        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf(
                'Pun invocation failed for %s (exit %d): %s',
                $testCase->fileName,
                $exitCode,
                implode("\n", $output),
            ));
        }

        return $this->parseOutput($testCase, implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    private function parseOutput(TestCase $testCase, string $output): array
    {
        /** @var mixed $data */
        $data = json_decode($output, true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Failed to parse Pun output for %s', $testCase->fileName));
        }

        $targetPath = realpath($testCase->path) ?: $testCase->path;
        $diagnostics = [];

        foreach ($data['files'] ?? [] as $filePath => $file) {
            if (!is_array($file)) {
                continue;
            }

            if ((string) $filePath !== $targetPath && (string) $filePath !== $testCase->path) {
                continue;
            }

            foreach ($file['messages'] ?? [] as $message) {
                if (!is_array($message)) {
                    continue;
                }

                $line = (int) ($message['line'] ?? 0);
                $text = trim((string) ($message['message'] ?? ''));
                $identifier = trim((string) ($message['identifier'] ?? ''));

                if ($line <= 0 || $text === '') {
                    continue;
                }

                $formatted = $identifier !== '' ? sprintf('%s [%s]', $text, $identifier) : $text;
                $diagnostics[$line] ??= [];
                $diagnostics[$line][] = $formatted;
            }
        }

        ksort($diagnostics);

        return $diagnostics;
    }
}
