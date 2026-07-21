<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

final class IntelephenseChecker implements Checker
{
    /**
     * Diagnostic codes dropped as non-type-conformance noise.
     * P1003 = "Symbol is declared but not used" (every test has unused params).
     *
     * @var list<string>
     */
    private const IGNORED_CODES = ['P1003'];

    public function __construct(
        private readonly string $nodeBinary,
        private readonly string $serverPath,
        private readonly string $clientPath,
        private readonly string $packageJsonPath,
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
        $args = array_map(
            static fn (string $path): string => escapeshellarg($path),
            [$this->clientPath, $this->serverPath, ...$testCase->supportPaths, $testCase->path],
        );

        $command = sprintf('%s %s 2>/dev/null', escapeshellarg($this->nodeBinary), implode(' ', $args));

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf('Intelephense invocation failed for %s', $testCase->fileName));
        }

        return $this->parseOutput($testCase, implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    private function parseOutput(TestCase $testCase, string $output): array
    {
        $output = trim($output);
        if ($output === '') {
            return [];
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($output, true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Failed to parse Intelephense output for %s', $testCase->fileName));
        }

        $diagnostics = [];
        foreach ($data['diagnostics'] ?? [] as $diagnostic) {
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

        return $diagnostics;
    }
}
