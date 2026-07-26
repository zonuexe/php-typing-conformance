<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for pzoom (https://github.com/muglug/pzoom), Matt Brown's Rust port
 * of Psalm. It reads Psalm's XML config and aims for Psalm compatibility, so in
 * the report it is folded into the `psalm` column and only surfaced where its
 * verdict diverges from Psalm's.
 *
 * pzoom's `--format json` is not wired up (text only), so we parse its console
 * output. Each finding is a line of the form:
 *   `ERROR: <IssueType> - <relpath>:<line>:<col> - <message> (see <url>)`
 * followed by a source-snippet line, which is ignored.
 */
final class PzoomChecker implements Checker
{
    public const DEFAULT_BINARY_PATH = '/Users/megurine/repo/rust/pzoom/target/release/pzoom';

    /** pzoom carries no `--version`; pin the announced release. */
    public const RELEASE_VERSION = '0.1.0';

    private readonly string $binaryPath;

    public function __construct(
        private readonly string $configPath,
        ?string $binaryPath = null,
    ) {
        $override = getenv('PZOOM_BIN');
        $this->binaryPath = (is_string($override) && $override !== '')
            ? $override
            : ($binaryPath ?? self::DEFAULT_BINARY_PATH);
    }

    public function name(): string
    {
        return 'pzoom';
    }

    public function version(): string
    {
        return 'pzoom ' . self::RELEASE_VERSION;
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
            '%s --config=%s analyze %s 2>/dev/null',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->configPath),
            implode(' ', $paths),
        );

        exec($command, $output, $exitCode);

        // 0 = clean, 2 = issues found (Psalm-style). Anything else is a failure.
        if ($exitCode !== 0 && $exitCode !== 1 && $exitCode !== 2) {
            throw new RuntimeException(sprintf('pzoom invocation failed for %s (exit %d)', $testCase->fileName, $exitCode));
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
            if (!preg_match(
                '/^(?:ERROR|WARNING|INFO):\s+(?<type>\S+)\s+-\s+(?<path>.+?):(?<line>\d+):(?<col>\d+)\s+-\s+(?<message>.+)$/',
                $line,
                $matches,
            )) {
                continue;
            }

            if (basename($matches['path']) !== $testCase->fileName) {
                continue;
            }

            $lineNumber = (int) $matches['line'];
            $message = trim((string) preg_replace('/\s*\(see https?:\/\/\S+\)\s*$/', '', $matches['message']));
            $type = trim($matches['type']);

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
