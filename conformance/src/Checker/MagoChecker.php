<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

final class MagoChecker implements Checker
{
    public function __construct(
        private readonly string $binaryPath,
        private readonly string $workspacePath,
    ) {
    }

    public function name(): string
    {
        return 'mago';
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' --version';
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine Mago version.');
        }

        return trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $relativePaths = array_map(
            fn (string $path): string => escapeshellarg($this->toWorkspaceRelativePath($path)),
            [...$testCase->supportPaths, $testCase->path],
        );
        $command = sprintf(
            '%s --workspace %s --colors never --php-version 8.5 analyze --reporting-format json %s 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->workspacePath),
            implode(' ', $relativePaths),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('Mago invocation failed for %s', $testCase->fileName));
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
            throw new RuntimeException(sprintf('Failed to parse Mago output for %s', $testCase->fileName));
        }

        $issues = $data['issues'] ?? [];
        if (!is_array($issues)) {
            return [];
        }

        $diagnostics = [];

        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }

            $annotations = $issue['annotations'] ?? [];
            if (!is_array($annotations) || $annotations === []) {
                continue;
            }

            $primary = $this->findPrimaryAnnotation($annotations);
            if ($primary === null) {
                continue;
            }

            $path = (string) ($primary['span']['file_id']['path'] ?? '');
            if ($path !== $testCase->path) {
                continue;
            }

            $lineNumber = (int) (($primary['span']['start']['line'] ?? -1) + 1);
            $message = trim((string) ($issue['message'] ?? ''));
            $code = trim((string) ($issue['code'] ?? ''));

            if ($lineNumber <= 0 || $message === '') {
                continue;
            }

            $formatted = $code !== '' ? sprintf('%s [%s]', $message, $code) : $message;
            $diagnostics[$lineNumber] ??= [];
            $diagnostics[$lineNumber][] = $formatted;
        }

        ksort($diagnostics);

        return $diagnostics;
    }

    /**
     * Mago talks around its own report: since 1.45 it prints `INFO` lines both
     * before the payload ("Overriding workspace directory with ...") and after
     * it ("No issues found."), on the same stream. So the object is cut out
     * rather than taken from the first brace to the end.
     */
    private function extractJsonPayload(string $output): string
    {
        $start = strpos($output, '{');
        if ($start === false) {
            if (str_contains($output, 'No issues found.')) {
                return '{"issues":[]}';
            }

            throw new RuntimeException('Mago output did not contain JSON payload.');
        }

        $end = strrpos($output, '}');
        if ($end === false || $end < $start) {
            throw new RuntimeException('Mago output did not contain a complete JSON payload.');
        }

        return substr($output, $start, $end - $start + 1);
    }

    /**
     * @param list<mixed> $annotations
     * @return array<string, mixed>|null
     */
    private function findPrimaryAnnotation(array $annotations): ?array
    {
        foreach ($annotations as $annotation) {
            if (is_array($annotation) && (($annotation['kind'] ?? null) === 'Primary')) {
                return $annotation;
            }
        }

        foreach ($annotations as $annotation) {
            if (is_array($annotation)) {
                return $annotation;
            }
        }

        return null;
    }

    private function toWorkspaceRelativePath(string $path): string
    {
        $prefix = rtrim($this->workspacePath, '/') . '/';
        if (!str_starts_with($path, $prefix)) {
            throw new RuntimeException(sprintf('Path %s is outside workspace %s', $path, $this->workspacePath));
        }

        return substr($path, strlen($prefix));
    }
}
