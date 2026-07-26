<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * PHPStan levels enable rule sets, they do not switch type inference on or off
 * (see `conf/config.level*.neon` in phpstan-src: every level only flips
 * reporting parameters such as `checkFunctionArgumentTypes` or
 * `checkMissingTypehints`). So "this file starts producing output at level N"
 * says nothing about how well a type is modelled — it only says which rule
 * happens to report it. To keep that distinction honest, this checker resolves
 * the reporting level of *each individual diagnostic* rather than stamping one
 * file-wide number onto every message.
 */
final class PhpStanChecker implements Checker
{
    private const MAX_LEVEL = 10;

    public function __construct(
        private readonly string $toolName,
        private readonly string $binaryPath,
        private readonly string $configPath,
        private readonly bool $resolveDiagnosticLevels,
    ) {
    }

    /** @var array<int, int> line number => lowest level reporting that line */
    private array $lineLevels = [];

    public function name(): string
    {
        return $this->toolName;
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' --version';
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine PHPStan version.');
        }

        return trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $this->lineLevels = [];

        $maxDiagnostics = $this->runAnalysis($testCase, 'max');

        if (!$this->resolveDiagnosticLevels || $maxDiagnostics === []) {
            return $maxDiagnostics;
        }

        $levels = $this->resolveMessageLevels($testCase, $maxDiagnostics);
        $this->lineLevels = $this->lowestLevelPerLine($maxDiagnostics, $levels);

        return $this->annotateLevels($maxDiagnostics, $levels);
    }

    /**
     * Lowest PHPStan level that reports each line, for the lines that are
     * reported at all. Empty when level resolution is disabled.
     *
     * @return array<int, int>
     */
    public function lineLevels(): array
    {
        return $this->lineLevels;
    }

    /**
     * Walk the levels upwards and record where each message shows up first.
     * Level configs are cumulative (`config.levelN.neon` includes level N-1),
     * so a message that appears at level N also appears at every level above
     * it; the walk can stop as soon as every max-level message is accounted
     * for.
     *
     * @param array<int, list<string>> $maxDiagnostics
     * @return array<string, int> message key => first level reporting it
     */
    private function resolveMessageLevels(TestCase $testCase, array $maxDiagnostics): array
    {
        $pending = [];
        foreach ($maxDiagnostics as $lineNumber => $messages) {
            foreach ($messages as $message) {
                $pending[$this->messageKey($lineNumber, $message)] = true;
            }
        }

        $levels = [];

        for ($level = 0; $level < self::MAX_LEVEL && $pending !== []; $level++) {
            $diagnostics = $this->runAnalysis($testCase, (string) $level);

            foreach ($diagnostics as $lineNumber => $messages) {
                foreach ($messages as $message) {
                    $key = $this->messageKey($lineNumber, $message);
                    if (!isset($pending[$key])) {
                        continue;
                    }

                    $levels[$key] = $level;
                    unset($pending[$key]);
                }
            }
        }

        // Whatever is still pending is only reported by the top level.
        foreach ($pending as $key => $_) {
            $levels[$key] = self::MAX_LEVEL;
        }

        return $levels;
    }

    /**
     * @param array<int, list<string>> $diagnostics
     * @param array<string, int> $levels
     * @return array<int, int>
     */
    private function lowestLevelPerLine(array $diagnostics, array $levels): array
    {
        $lineLevels = [];

        foreach ($diagnostics as $lineNumber => $messages) {
            foreach ($messages as $message) {
                $level = $levels[$this->messageKey($lineNumber, $message)] ?? null;
                if ($level === null) {
                    continue;
                }

                $lineLevels[$lineNumber] = min($lineLevels[$lineNumber] ?? $level, $level);
            }
        }

        ksort($lineLevels);

        return $lineLevels;
    }

    /**
     * @param array<int, list<string>> $diagnostics
     * @param array<string, int> $levels
     * @return array<int, list<string>>
     */
    private function annotateLevels(array $diagnostics, array $levels): array
    {
        $annotated = [];

        foreach ($diagnostics as $lineNumber => $messages) {
            $annotated[$lineNumber] = [];
            foreach ($messages as $message) {
                $level = $levels[$this->messageKey($lineNumber, $message)] ?? null;
                $annotated[$lineNumber][] = $level === null
                    ? $message
                    : sprintf('%s [reported-from-level=%d]', $message, $level);
            }
        }

        return $annotated;
    }

    private function messageKey(int $lineNumber, string $message): string
    {
        return $lineNumber . "\t" . $message;
    }

    /**
     * @param list<string> $output
     * @return array<int, list<string>>
     */
    private function parseOutput(TestCase $testCase, array $output): array
    {
        $diagnostics = [];

        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $prefix = $testCase->path . ':';
            if (!str_starts_with($line, $prefix)) {
                continue;
            }

            $rest = substr($line, strlen($prefix));
            if ($rest === false) {
                continue;
            }

            [$lineNumberText, $message] = explode(':', $rest, 2) + [null, null];
            if ($lineNumberText === null || $message === null) {
                continue;
            }

            $lineNumber = (int) trim($lineNumberText);
            $diagnostics[$lineNumber] ??= [];
            $diagnostics[$lineNumber][] = trim($message);
        }

        ksort($diagnostics);

        return $diagnostics;
    }

    /**
     * @return array<int, list<string>>
     */
    private function runAnalysis(TestCase $testCase, string $level): array
    {
        $paths = array_map(
            static fn (string $path): string => escapeshellarg($path),
            [...$testCase->supportPaths, $testCase->path],
        );

        $command = sprintf(
            '%s analyse -c %s --level=%s --no-progress --error-format=raw %s 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->configPath),
            escapeshellarg($level),
            implode(' ', $paths),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('PHPStan invocation failed for %s', $testCase->fileName));
        }

        return $this->parseOutput($testCase, $output);
    }
}
