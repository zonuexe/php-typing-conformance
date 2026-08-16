<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for Phan (https://github.com/phan/phan).
 *
 * Unlike the other CLI checkers this one runs once for the whole corpus rather
 * than once per test. That is not an approximation: Phan was already being
 * handed `--directory <testsDir>` on every invocation, and a positional file
 * argument does not narrow what Phan analyses — it only adds to the file list,
 * which the directory already contained. Both shapes emit the identical 471
 * diagnostics for the current corpus, byte for byte; the per-file run then
 * threw away all but one file's worth. Running once and slicing the result
 * keeps every measurement and drops the 218 process starts that dominated the
 * cost.
 *
 * Slicing means the corpus is analysed as one project, which is also how Phan
 * saw it before, so project-wide issues (duplicate declarations, cross-file
 * references) land on the same file and line they always did.
 */
final class PhanChecker implements Checker
{
    /**
     * Absolute file path → line → messages, for the whole corpus.
     *
     * @var array<string, array<int, list<string>>>|null
     */
    private ?array $report = null;

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
        return $this->report()[$testCase->path] ?? [];
    }

    /**
     * The whole-corpus run, performed on first use and kept for the rest of the
     * process.
     *
     * @return array<string, array<int, list<string>>>
     */
    private function report(): array
    {
        if ($this->report !== null) {
            return $this->report;
        }

        $command = sprintf(
            '%s --allow-polyfill-parser --target-php-version 8.5 --output-mode text --directory %s 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->testsDir),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException('Phan invocation failed for the test corpus.');
        }

        return $this->report = $this->parseOutput($output);
    }

    /**
     * @param list<string> $output
     * @return array<string, array<int, list<string>>>
     */
    private function parseOutput(array $output): array
    {
        $diagnostics = [];
        $prefix = rtrim($this->testsDir, '/') . '/';

        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '' || !str_starts_with($line, $prefix)) {
                continue;
            }

            // <path>:<line> <IssueType> <message>, where the path may not be
            // directly in $testsDir but is always under it.
            if (!preg_match('/^(.+?):(\d+)\s+(\S+)\s+(.*)$/', $line, $matches)) {
                continue;
            }

            $path = $matches[1];
            $lineNumber = (int) $matches[2];
            $issueType = $matches[3];
            $message = trim($matches[4]);

            $diagnostics[$path][$lineNumber] ??= [];
            $diagnostics[$path][$lineNumber][] = sprintf('%s [%s]', $message, $issueType);
        }

        foreach ($diagnostics as &$byLine) {
            ksort($byLine);
        }
        unset($byLine);

        return $diagnostics;
    }
}
