<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

final class PhpStanChecker implements Checker
{
    public function __construct(
        private readonly string $toolName,
        private readonly string $binaryPath,
        private readonly string $configPath,
        private readonly bool $stopAtFirstDetectedLevel,
    ) {
    }

    private ?int $firstDetectedLevel = null;
    private ?int $knownFirstDetectedLevel = null;

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
        $this->firstDetectedLevel = null;

        if ($this->stopAtFirstDetectedLevel) {
            return $this->runUntilFirstDetectedLevel($testCase);
        }

        return $this->runAnalysis($testCase, $this->configPath, 'max');
    }

    public function firstDetectedLevel(): ?int
    {
        return $this->firstDetectedLevel;
    }

    public function setKnownFirstDetectedLevel(?int $level): void
    {
        $this->knownFirstDetectedLevel = $level;
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
    private function runAnalysis(TestCase $testCase, string $configPath, string $level): array
    {
        $paths = array_map(
            static fn (string $path): string => escapeshellarg($path),
            [...$testCase->supportPaths, $testCase->path],
        );

        $command = sprintf(
            '%s analyse -c %s --level=%s --no-progress --error-format=raw %s 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($configPath),
            escapeshellarg($level),
            implode(' ', $paths),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('PHPStan invocation failed for %s', $testCase->fileName));
        }

        return $this->parseOutput($testCase, $output);
    }

    /**
     * @return array<int, list<string>>
     */
    private function runUntilFirstDetectedLevel(TestCase $testCase): array
    {
        if ($this->knownFirstDetectedLevel !== null) {
            $knownDiagnostics = $this->runKnownLevelCheck($testCase, $this->knownFirstDetectedLevel);
            if ($knownDiagnostics !== null) {
                return $knownDiagnostics;
            }
        }

        for ($level = 0; $level <= 10; $level++) {
            $diagnostics = $this->runAnalysis(
                $testCase,
                $this->configPath,
                $level === 10 ? 'max' : (string) $level,
            );

            if ($diagnostics === []) {
                continue;
            }

            $this->firstDetectedLevel = $level;

            return $this->annotateDetectionLevel($diagnostics, $level);
        }

        return [];
    }

    /**
     * @return array<int, list<string>>|null
     */
    private function runKnownLevelCheck(TestCase $testCase, int $knownLevel): ?array
    {
        $knownDiagnostics = $this->runAnalysis(
            $testCase,
            $this->configPath,
            $knownLevel === 10 ? 'max' : (string) $knownLevel,
        );

        if ($knownDiagnostics === []) {
            return null;
        }

        if ($knownLevel > 0) {
            $previousDiagnostics = $this->runAnalysis(
                $testCase,
                $this->configPath,
                (string) ($knownLevel - 1),
            );

            if ($previousDiagnostics !== []) {
                return null;
            }
        }

        $this->firstDetectedLevel = $knownLevel;

        return $this->annotateDetectionLevel($knownDiagnostics, $knownLevel);
    }

    /**
     * @param array<int, list<string>> $diagnostics
     * @return array<int, list<string>>
     */
    private function annotateDetectionLevel(array $diagnostics, int $level): array
    {
        $annotatedDiagnostics = [];

        foreach ($diagnostics as $lineNumber => $messages) {
            $annotatedDiagnostics[$lineNumber] = [];
            foreach ($messages as $message) {
                $annotatedDiagnostics[$lineNumber][] = sprintf(
                    '%s [detected-from-level=%d]',
                    $message,
                    $level,
                );
            }
        }

        return $annotatedDiagnostics;
    }
}
