<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for Steins (https://github.com/rigortype/steins), a Rust PHP
 * static analyzer.
 *
 * Steins is a zero-false-positive proof-layer tool: it reports only provable
 * runtime breakage (TypeErrors on live paths) and effect-envelope violations,
 * so it legitimately stays silent on many `// E` expectations that
 * declared-type worst-case analyzers (PHPStan-class tools) report. Silence is
 * an honest verdict, not a miss.
 *
 * CLI contract:
 *   steins check <paths...> --format json
 *     -> stdout JSON: {"findings":[{"id","path","line","column","message"}...],
 *                      "suppressed":N,"baselined":N,"vendor_suppressed":N}
 *     -> exit 0 = clean, exit 1 = findings.
 *
 * Steins analyses the given paths as ONE project (PHP >= 8.1 semantics,
 * `declare(strict_types=1)` respected per file). Support files (`_`-prefixed)
 * are passed alongside the primary file so cross-file symbols resolve;
 * diagnostics are then filtered down to the primary test file.
 *
 * Installed as a Composer package (typedduck/steins) like the other analyzers
 * this suite pins. Its `vendor/bin/steins` is a PHP launcher that downloads
 * the platform binary on first use and caches it per version and target, so
 * the first invocation of a new release prints progress — to stderr, which is
 * dropped everywhere here so it can never reach the parsed output.
 *
 * Binary path resolution: the `STEINS_BIN` environment variable overrides the
 * constructor default when set and non-empty.
 */
final class SteinsChecker implements Checker
{
    private readonly string $binaryPath;

    public function __construct(string $binaryPath)
    {
        // STEINS_BIN points the suite at a local build instead of the pinned
        // Composer release.
        $override = getenv('STEINS_BIN');

        $this->binaryPath = is_string($override) && $override !== '' ? $override : $binaryPath;
    }

    public function name(): string
    {
        return 'steins';
    }

    public function version(): string
    {
        // `steins version`, not `--version`: the CLI takes subcommands only.
        // Its banner runs to four lines, of which only the first carries
        // version information -- the rest is a copyright notice and a pointer
        // to `steins license`. stderr is dropped so a first-run download
        // never lands in the recorded version.
        $command = escapeshellarg($this->binaryPath) . ' version 2>/dev/null';
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine Steins version.');
        }

        foreach ($output as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'steins ')) {
                return $line;
            }
        }

        throw new RuntimeException('Steins reported no version line.');
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        // Absolute paths so the returned `path` fields match $testCase->path
        // exactly when filtering. Support files ride along as one project.
        $paths = array_map(
            static fn (string $path): string => escapeshellarg($path),
            [...$testCase->supportPaths, $testCase->path],
        );

        // `--profile contracts`: conformance measures the checker's full
        // capability surface. Steins' default profile is deliberately
        // proof-layer-only (quiet default; ADR-0050), which would hide the
        // contract-layer findings (`phpdoc.*` etc.) many cases assert on.
        $command = sprintf(
            '%s check --profile contracts --format json %s',
            escapeshellarg($this->binaryPath),
            implode(' ', $paths),
        );

        exec($command . ' 2>/dev/null', $output, $exitCode);

        // 0 = clean, 1 = findings. Anything else is a real invocation failure.
        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('Steins invocation failed for %s (exit %d)', $testCase->fileName, $exitCode));
        }

        return $this->parseOutput($testCase, implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    private function parseOutput(TestCase $testCase, string $output): array
    {
        $start = strpos($output, '{');
        if ($start === false) {
            throw new RuntimeException(sprintf('Steins output did not contain JSON payload for %s', $testCase->fileName));
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode(substr($output, $start), true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Failed to parse Steins output for %s', $testCase->fileName));
        }

        $findings = $data['findings'] ?? [];
        if (!is_array($findings)) {
            return [];
        }

        $diagnostics = [];

        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                continue;
            }

            $path = (string) ($finding['path'] ?? '');
            if ($path !== $testCase->path) {
                continue;
            }

            $lineNumber = (int) ($finding['line'] ?? -1);
            $message = trim((string) ($finding['message'] ?? ''));
            $id = trim((string) ($finding['id'] ?? ''));

            if ($lineNumber <= 0 || $message === '') {
                continue;
            }

            $formatted = $id !== '' ? sprintf('%s [%s]', $message, $id) : $message;
            $diagnostics[$lineNumber] ??= [];
            $diagnostics[$lineNumber][] = $formatted;
        }

        ksort($diagnostics);

        return $diagnostics;
    }
}
