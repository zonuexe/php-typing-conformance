<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

final class PhanChecker implements Checker
{
    public function __construct(
        private readonly string $binaryPath,
        private readonly string $testsDir,
    ) {
    }

    public function name(): string
    {
        return 'phan';
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' --version';
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine Phan version.');
        }

        $lines = array_values(array_filter(
            $output,
            static fn (string $line): bool => trim($line) !== '' && !str_contains($line, 'php-ast is not installed'),
        ));

        return trim(implode("\n", $lines));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $command = sprintf(
            '%s --allow-polyfill-parser --output-mode text --directory %s %s 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->testsDir),
            escapeshellarg($testCase->path),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('Phan invocation failed for %s', $testCase->fileName));
        }

        return $this->parseOutput($testCase, $output);
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

            if (!preg_match('/^(\d+)\s+(\S+)\s+(.*)$/', $rest, $matches)) {
                continue;
            }

            $lineNumber = (int) $matches[1];
            $issueType = $matches[2];
            $message = trim($matches[3]);

            $formatted = sprintf('%s [%s]', $message, $issueType);
            $diagnostics[$lineNumber] ??= [];
            $diagnostics[$lineNumber][] = $formatted;
        }

        ksort($diagnostics);

        return $diagnostics;
    }
}
