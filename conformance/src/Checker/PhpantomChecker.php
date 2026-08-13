<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * PHPantom's `analyze` CLI, the same engine the LSP probes drive.
 *
 * One path per invocation, so the analysis is the test file alone — the
 * corpus carries no fixture support files, which keeps the single-path
 * limitation invisible. Diagnostics arrive as JSON on stdout with the
 * identifier as a machine tag; exit code 1 means findings were made and is
 * treated as success, like Psalm's exit 2. Parse errors are ordinary
 * diagnostics (`syntax_error`) rather than a separate channel.
 */
final class PhpantomChecker implements Checker
{
    public function __construct(
        private readonly string $binaryPath,
    ) {
    }

    public function name(): string
    {
        return 'phpantom';
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' --version';
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine PHPantom version.');
        }

        return trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $command = sprintf(
            '%s analyze --format json --no-colour %s',
            escapeshellarg($this->binaryPath),
            escapeshellarg($testCase->path),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('PHPantom invocation failed for %s', $testCase->fileName));
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
            throw new RuntimeException(sprintf('Failed to parse PHPantom output for %s', $testCase->fileName));
        }

        $diagnostics = [];

        foreach ($data['files'] ?? [] as $filePath => $file) {
            // The JSON keys the path as given; match by basename so a
            // relative invocation cannot silently lose the file.
            if (!str_ends_with((string) $filePath, $testCase->fileName)) {
                continue;
            }

            foreach ($file['messages'] ?? [] as $message) {
                if (!is_array($message)) {
                    continue;
                }

                $line = (int) ($message['line'] ?? 0);
                $text = trim((string) ($message['message'] ?? ''));
                $identifier = trim((string) ($message['identifier'] ?? ''));

                if ($line <= 0 || $text === '') {
                    continue;
                }

                $formatted = $identifier !== '' ? sprintf('%s [%s]', $text, $identifier) : $text;
                $diagnostics[$line] ??= [];
                $diagnostics[$line][] = $formatted;
            }
        }

        ksort($diagnostics);

        return $diagnostics;
    }
}
