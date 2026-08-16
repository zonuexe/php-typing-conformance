<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for Intelephense (https://intelephense.com/), which is a language
 * server rather than a CLI, and so is driven over LSP by
 * {@see intelephense-client.mjs}.
 *
 * The measurement is taken for the whole corpus in one server session and
 * sliced per test, the way {@see QodanaChecker} reads one report. Intelephense
 * spends almost all of its time starting up and indexing — a single test cost
 * ~2.1 seconds of which the analysis was milliseconds — so one session for 218
 * tests replaced 218 sessions and took the column from ~467 seconds to under
 * four. The client documents the two constraints that make a shared session
 * give the same answers as separate ones (one open document at a time, first
 * publish wins); a per-file diff against the stored results confirmed it
 * changes no diagnostic.
 */
final class IntelephenseChecker implements Checker
{
    /**
     * Diagnostic codes dropped as non-type-conformance noise.
     * P1003 = "Symbol is declared but not used" (every test has unused params).
     *
     * @var list<string>
     */
    private const IGNORED_CODES = ['P1003'];

    /**
     * The whole corpus, keyed by file name. Built on the first analysis.
     *
     * @var array<string, array<int, list<string>>>|null
     */
    private ?array $report = null;

    public function __construct(
        private readonly string $nodeBinary,
        private readonly string $serverPath,
        private readonly string $clientPath,
        private readonly string $packageJsonPath,
        private readonly string $testsDir,
    ) {
    }

    public function name(): string
    {
        return 'intelephense';
    }

    public function version(): string
    {
        $contents = @file_get_contents($this->packageJsonPath);
        if ($contents === false) {
            return 'unknown';
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($contents, true);

        return 'intelephense ' . (string) ($data['version'] ?? 'unknown');
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

        $command = sprintf(
            '%s %s %s %s 2>/dev/null',
            escapeshellarg($this->nodeBinary),
            escapeshellarg($this->clientPath),
            escapeshellarg($this->serverPath),
            escapeshellarg($this->testsDir),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Intelephense invocation failed for the test corpus.');
        }

        return $this->report = $this->parseOutput(implode("\n", $output));
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    private function parseOutput(string $output): array
    {
        $output = trim($output);
        if ($output === '') {
            throw new RuntimeException('Intelephense produced no output for the test corpus.');
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($output, true);
        if (!is_array($data) || !is_array($data['diagnostics'] ?? null)) {
            throw new RuntimeException('Failed to parse Intelephense output for the test corpus.');
        }

        $report = [];

        /** @var mixed $fileDiagnostics */
        foreach ($data['diagnostics'] as $fileName => $fileDiagnostics) {
            if (!is_string($fileName) || !is_array($fileDiagnostics)) {
                continue;
            }

            $diagnostics = [];
            foreach ($fileDiagnostics as $diagnostic) {
                if (!is_array($diagnostic)) {
                    continue;
                }

                $code = trim((string) ($diagnostic['code'] ?? ''));
                if (in_array($code, self::IGNORED_CODES, true)) {
                    continue;
                }

                $line = (int) ($diagnostic['line'] ?? 0);
                $message = trim((string) ($diagnostic['message'] ?? ''));
                if ($line <= 0 || $message === '') {
                    continue;
                }

                $formatted = $code !== '' ? sprintf('%s [%s]', $message, $code) : $message;
                $diagnostics[$line] ??= [];
                $diagnostics[$line][] = $formatted;
            }

            ksort($diagnostics);
            $report[$fileName] = $diagnostics;
        }

        return $report;
    }
}
