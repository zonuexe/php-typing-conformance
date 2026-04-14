<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

final class NoVerifyChecker implements Checker
{
    public function __construct(
        private readonly string $binaryPath,
    ) {
    }

    public function name(): string
    {
        return 'noverify';
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' version';
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine NoVerify version.');
        }

        return trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $analysisFiles = [...$testCase->supportPaths, $testCase->path];
        $arguments = array_map(static fn (string $path): string => escapeshellarg($path), $analysisFiles);
        $command = sprintf(
            '%s check --output-json --full-analysis-files %s %s 2>&1',
            escapeshellarg($this->binaryPath),
            implode(' ', $arguments),
            escapeshellarg($testCase->path),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 2) {
            throw new RuntimeException(sprintf('NoVerify invocation failed for %s', $testCase->fileName));
        }

        return $this->parseOutput($testCase, implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    private function parseOutput(TestCase $testCase, string $output): array
    {
        $json = $this->extractJsonPayload($output);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Failed to parse NoVerify output for %s', $testCase->fileName));
        }

        $reports = $data['Reports'] ?? [];
        if (!is_array($reports)) {
            return [];
        }

        $diagnostics = [];

        foreach ($reports as $report) {
            if (!is_array($report)) {
                continue;
            }

            $fileName = (string) ($report['filename'] ?? '');
            if ($fileName !== $testCase->path) {
                continue;
            }

            $lineNumber = (int) ($report['line'] ?? 0);
            $message = trim((string) ($report['message'] ?? ''));
            $checkName = trim((string) ($report['check_name'] ?? ''));

            if ($lineNumber <= 0 || $message === '') {
                continue;
            }

            $formatted = $checkName !== '' ? sprintf('%s [%s]', $message, $checkName) : $message;
            $diagnostics[$lineNumber] ??= [];
            $diagnostics[$lineNumber][] = $formatted;
        }

        ksort($diagnostics);

        return $diagnostics;
    }

    private function extractJsonPayload(string $output): string
    {
        foreach (preg_split("/\r?\n/", $output) as $line) {
            $line = trim($line);
            if ($line !== '' && str_starts_with($line, '{')) {
                return $line;
            }
        }

        throw new RuntimeException('NoVerify output did not contain JSON payload.');
    }
}
