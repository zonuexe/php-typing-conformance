<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Phpactor, driven through its `worse:analyse` command.
 *
 * Like PhpantomChecker, the whole corpus is analysed once and the result
 * indexed by file rather than invoked per test — `worse:analyse` pays a
 * fixed reflection-bootstrap cost per run, so a per-test invocation would
 * multiply that ~215 times for no benefit.
 *
 * `worse:analyse --format=json` prints one JSON object per line (not a JSON
 * array). Progress went to the same stream until 2026.06.23.0, which moved
 * it to STDERR; anything that is not a JSON object is still skipped. From
 * that release the object also carries `line`, `col`, `code`, and a
 * readable `severity` string — previously `range.start` was a raw byte
 * offset and `severity` was always `{}`. The adapter prefers `line` and
 * falls back to resolving the offset against the file, which is still the
 * one case in the suite that has to read the source to place a diagnostic.
 *
 * Paths come back relative to the *invoking* working directory rather than to
 * `--working-dir`, so matching is by basename, the same accommodation
 * `PzoomChecker` makes for Psalm-compatible tools that don't control their own
 * path formatting.
 *
 * The run is preceded by `index:build`, and that is not an optimization. The
 * index lives outside the repository, in the user's cache directory, keyed by
 * the working directory's path. Against a cold index `worse:analyse` cannot
 * resolve symbols it has not been told about — including functions declared in
 * the very file under analysis — and answers with twenty times the diagnostics,
 * nearly all of them `Function "…" not found`. Building first is what makes the
 * column a property of the corpus rather than of whatever else has been run on
 * this machine.
 */
final class PhpactorChecker implements Checker
{
    /** @var array<string, array<int, list<string>>>|null */
    private ?array $corpus = null;

    /** @var array<string, string> file contents, keyed by basename */
    private array $sources = [];

    public function __construct(
        private readonly string $binaryPath,
        private readonly string $workspacePath,
        private readonly string $testsDir,
    ) {
    }

    public function name(): string
    {
        return 'phpactor';
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' --version 2>&1';
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine Phpactor version.');
        }

        return trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $this->corpus ??= $this->analyseCorpus();

        return $this->corpus[basename($testCase->path)] ?? [];
    }

    /**
     * Populate the project index the analysis reads from.
     *
     * Incremental after the first build, and cheap enough to run every time
     * that leaving it to the operator would be a worse trade: a stale index is
     * indistinguishable from a tool with an opinion.
     */
    private function buildIndex(): void
    {
        $command = sprintf(
            '%s index:build --no-interaction --working-dir=%s > /dev/null 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->workspacePath),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Phpactor index build failed.');
        }
    }

    /**
     * @return array<string, array<int, list<string>>> basename => line => messages
     */
    private function analyseCorpus(): array
    {
        $this->buildIndex();

        $command = sprintf(
            '%s worse:analyse --no-interaction --format=json --working-dir=%s %s 2>/dev/null',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->workspacePath),
            escapeshellarg($this->testsDir),
        );

        exec($command, $output, $exitCode);

        // 0 clean, 1 issues found.
        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException('Phpactor invocation failed.');
        }

        $byFile = [];

        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            /** @var mixed $entry */
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                // Progress used to share stdout; skip any leftover chatter.
                continue;
            }

            $path = (string) ($entry['file'] ?? '');
            $message = trim((string) ($entry['message'] ?? ''));
            $code = trim((string) ($entry['code'] ?? ''));
            $lineNumber = is_int($entry['line'] ?? null) ? $entry['line'] : 0;
            $range = $entry['range'] ?? null;
            $offset = is_array($range) ? (int) ($range['start'] ?? -1) : -1;

            if ($path === '' || $message === '') {
                continue;
            }

            $file = basename($path);
            if (!is_file($this->testsDir . '/' . $file)) {
                continue;
            }

            if ($lineNumber < 1) {
                if ($offset < 0) {
                    continue;
                }

                $lineNumber = $this->lineAtOffset($file, $offset);
                if ($lineNumber === null) {
                    continue;
                }
            }

            $byFile[$file][$lineNumber] ??= [];
            $byFile[$file][$lineNumber][] = $code === '' ? $message : sprintf('%s [%s]', $message, $code);
        }

        foreach ($byFile as $file => $diagnostics) {
            ksort($diagnostics);
            $byFile[$file] = $diagnostics;
        }

        return $byFile;
    }

    /**
     * Resolve a byte offset to a 1-based line number in the analysed file.
     *
     * Returns null when the file is not one of ours — `worse:analyse` follows
     * reflection into stubs and vendor code, and a diagnostic reported there
     * is not a result about the corpus.
     */
    private function lineAtOffset(string $file, int $offset): ?int
    {
        if (!array_key_exists($file, $this->sources)) {
            $path = $this->testsDir . '/' . $file;
            $contents = is_file($path) ? file_get_contents($path) : false;
            $this->sources[$file] = $contents === false ? '' : $contents;
        }

        $contents = $this->sources[$file];
        if ($contents === '') {
            return null;
        }

        $offset = min($offset, strlen($contents));

        return substr_count($contents, "\n", 0, $offset) + 1;
    }
}
