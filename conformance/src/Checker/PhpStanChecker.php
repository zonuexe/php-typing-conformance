<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

final class PhpStanChecker implements Checker
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $binaryPath,
        private readonly string $configPath,
        private readonly string $noStrictConfigPath,
    ) {
    }

    public function name(): string
    {
        return 'phpstan';
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
        $strictDiagnostics = $this->runAnalysis($testCase, $this->configPath);
        $noStrictDiagnostics = $this->runAnalysis($testCase, $this->noStrictConfigPath);

        return $this->markOptInDiagnostics($strictDiagnostics, $noStrictDiagnostics);
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
    private function runAnalysis(TestCase $testCase, string $configPath): array
    {
        $command = sprintf(
            '%s analyse -c %s --no-progress --error-format=raw %s 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($configPath),
            escapeshellarg($testCase->path),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('PHPStan invocation failed for %s', $testCase->fileName));
        }

        return $this->parseOutput($testCase, $output);
    }

    /**
     * @param array<int, list<string>> $strictDiagnostics
     * @param array<int, list<string>> $noStrictDiagnostics
     * @return array<int, list<string>>
     */
    private function markOptInDiagnostics(array $strictDiagnostics, array $noStrictDiagnostics): array
    {
        $annotatedDiagnostics = [];

        foreach ($strictDiagnostics as $lineNumber => $messages) {
            $annotatedDiagnostics[$lineNumber] = [];
            $remainingNoStrict = $noStrictDiagnostics[$lineNumber] ?? [];

            foreach ($messages as $message) {
                $matchIndex = array_search($message, $remainingNoStrict, true);
                if ($matchIndex === false) {
                    $message .= ' [opt-in: phpstan-strict-rules]';
                } else {
                    unset($remainingNoStrict[$matchIndex]);
                }

                $annotatedDiagnostics[$lineNumber][] = $message;
            }
        }

        return $annotatedDiagnostics;
    }
}
