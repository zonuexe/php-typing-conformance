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
 *
 * Like {@see PsalmChecker}, the corpus is analysed in one whole-directory run
 * and the answer is sliced per test case. pzoom needs the directory spelled out
 * — with no path argument it analyses the current working directory, which is
 * the whole repository including `vendor/` — but given `tests/` it reproduces
 * the per-file verdict exactly, for every case, and takes a quarter of a second
 * instead of fifty. Unlike Psalm it grew no extra whole-project issues to
 * suppress: pzoom implements neither the finality nor the immutability nag.
 */
final class PzoomChecker implements Checker
{
    public const DEFAULT_BINARY_PATH = '/Users/megurine/repo/rust/pzoom/target/release/pzoom';

    /** pzoom carries no `--version`; pin the announced release. */
    public const RELEASE_VERSION = '0.1.0';

    private readonly string $binaryPath;

    /**
     * Diagnostics for the whole corpus, keyed by file basename — pzoom reports
     * paths relative to the config file, so basenames are what can be matched,
     * and the corpus is one flat directory. Null until first asked for.
     *
     * @var array<string, array<int, list<string>>>|null
     */
    private ?array $report = null;

    public function __construct(
        private readonly string $configPath,
        private readonly string $testsDir,
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
        return $this->report()[$testCase->fileName] ?? [];
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    private function report(): array
    {
        if ($this->report !== null) {
            return $this->report;
        }

        $command = sprintf(
            '%s --config=%s analyze %s 2>/dev/null',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->configPath),
            escapeshellarg($this->testsDir),
        );

        exec($command, $output, $exitCode);

        // 0 = clean, 2 = issues found (Psalm-style). Anything else is a failure,
        // and because this is one run for the whole column, a failure here takes
        // down every test rather than one.
        if ($exitCode !== 0 && $exitCode !== 1 && $exitCode !== 2) {
            throw new RuntimeException(sprintf('pzoom invocation failed (exit %d)', $exitCode));
        }

        return $this->report = $this->parseOutput($output);
    }

    /**
     * @param list<string> $output
     * @return array<string, array<int, list<string>>>
     */
    private function parseOutput(array $output): array
    {
        $byFile = [];

        foreach ($output as $line) {
            if (!preg_match(
                '/^(?:ERROR|WARNING|INFO):\s+(?<type>\S+)\s+-\s+(?<path>.+?):(?<line>\d+):(?<col>\d+)\s+-\s+(?<message>.+)$/',
                $line,
                $matches,
            )) {
                continue;
            }

            $fileName = basename($matches['path']);
            $lineNumber = (int) $matches['line'];
            $message = trim((string) preg_replace('/\s*\(see https?:\/\/\S+\)\s*$/', '', $matches['message']));
            $type = trim($matches['type']);

            if ($lineNumber <= 0 || $message === '') {
                continue;
            }

            $formatted = $type !== '' ? sprintf('%s [%s]', $message, $type) : $message;
            $byFile[$fileName][$lineNumber] ??= [];
            $byFile[$fileName][$lineNumber][] = $formatted;
        }

        foreach ($byFile as &$diagnostics) {
            ksort($diagnostics);
        }

        return $byFile;
    }
}
