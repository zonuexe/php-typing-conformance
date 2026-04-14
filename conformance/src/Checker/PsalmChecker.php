<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

final class PsalmChecker implements Checker
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $binaryPath,
        private readonly string $configPath,
    ) {
    }

    public function name(): string
    {
        return 'psalm';
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' --version';
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine Psalm version.');
        }

        return trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $paths = array_map(
            static fn (string $path): string => escapeshellarg($path),
            [...$testCase->supportPaths, $testCase->path],
        );

        $command = sprintf(
            '%s --config=%s --no-progress --output-format=json %s 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->configPath),
            implode(' ', $paths),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 2) {
            throw new RuntimeException(sprintf('Psalm invocation failed for %s', $testCase->fileName));
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
            throw new RuntimeException(sprintf('Failed to parse Psalm output for %s', $testCase->fileName));
        }

        $issues = array_is_list($data) ? $data : ($data['issues'] ?? []);
        if (!is_array($issues)) {
            return [];
        }

        $diagnostics = [];

        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }

            $filePath = (string) ($issue['file_path'] ?? '');
            if ($filePath !== $testCase->path) {
                continue;
            }

            $lineNumber = (int) ($issue['line_from'] ?? 0);
            $message = trim((string) ($issue['message'] ?? ''));
            $type = trim((string) ($issue['type'] ?? ''));

            if ($lineNumber <= 0 || $message === '') {
                continue;
            }

            $formatted = $type !== '' ? sprintf('%s [%s]', $message, $type) : $message;
            $diagnostics[$lineNumber] ??= [];
            $diagnostics[$lineNumber][] = $formatted;
        }

        ksort($diagnostics);

        return $diagnostics;
    }
}
