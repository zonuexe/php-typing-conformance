<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * PHPStan levels enable rule sets, they do not switch type inference on or off
 * (see `conf/config.level*.neon` in phpstan-src: every level only flips
 * reporting parameters such as `checkFunctionArgumentTypes` or
 * `checkMissingTypehints`). So "this file starts producing output at level N"
 * says nothing about how well a type is modelled — it only says which rule
 * happens to report it. To keep that distinction honest, this checker resolves
 * the reporting level of *each individual diagnostic* rather than stamping one
 * file-wide number onto every message.
 *
 * Resolving a level means asking PHPStan again, and PHPStan spends its time
 * booting rather than analysing: one file and the whole suite both take about
 * 1.3 seconds. Walking the levels per test therefore cost ~585 invocations for
 * ~100 tests. The walk now happens once for the whole test set -- ten runs,
 * one per level, indexed by file and message -- while the authoritative pass
 * stays per test.
 *
 * That split is deliberate. The per-test pass at max is what the matrix
 * measures, and analysing each test alone is what makes it a measurement of
 * that test; the shared walk only answers "at which level does this known
 * message first appear", which the level configs make scope-independent for
 * every rule that looks at one file. Rules that count across a project are the
 * exception -- "Trait X is used zero times" means something different when the
 * project is one file -- so a message the shared index has never seen falls
 * back to the old per-test walk rather than being guessed at.
 */
final class PhpStanChecker implements Checker
{
    private const MAX_LEVEL = 10;

    public function __construct(
        private readonly string $toolName,
        private readonly string $binaryPath,
        private readonly string $configPath,
        private readonly string $testsDir,
        private readonly bool $resolveDiagnosticLevels,
    ) {
    }

    /** @var array<int, int> line number => lowest level reporting that line */
    private array $lineLevels = [];

    /**
     * Built once, on the first test that needs it.
     *
     * @var array<string, int>|null "file\tline\tmessage" => lowest level
     */
    private ?array $levelIndex = null;

    public function name(): string
    {
        return $this->toolName;
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' --version';
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine PHPStan version.');
        }

        return trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        $this->lineLevels = [];

        $maxDiagnostics = $this->runAnalysis($testCase, 'max');

        if (!$this->resolveDiagnosticLevels || $maxDiagnostics === []) {
            return $maxDiagnostics;
        }

        $levels = $this->resolveMessageLevels($testCase, $maxDiagnostics);
        $this->lineLevels = $this->lowestLevelPerLine($maxDiagnostics, $levels);

        return $this->annotateLevels($maxDiagnostics, $levels);
    }

    /**
     * Lowest PHPStan level that reports each line, for the lines that are
     * reported at all. Empty when level resolution is disabled.
     *
     * @return array<int, int>
     */
    public function lineLevels(): array
    {
        return $this->lineLevels;
    }

    /**
     * Where each of this test's diagnostics first appears in the level ladder.
     *
     * @param array<int, list<string>> $maxDiagnostics
     * @return array<string, int> message key => first level reporting it
     */
    private function resolveMessageLevels(TestCase $testCase, array $maxDiagnostics): array
    {
        $this->levelIndex ??= $this->buildLevelIndex();

        $levels = [];
        $pending = [];

        foreach ($maxDiagnostics as $lineNumber => $messages) {
            foreach ($messages as $message) {
                $key = $this->messageKey($lineNumber, $message);
                $level = $this->levelIndex[$testCase->fileName . "\t" . $key] ?? null;

                if ($level === null) {
                    // Not seen when the whole suite is analysed together, so
                    // this test has to be asked on its own -- see the note on
                    // project-wide rules in the class docblock.
                    $pending[$key] = true;
                    continue;
                }

                $levels[$key] = $level;
            }
        }

        return $pending === [] ? $levels : $levels + $this->walkLevels($testCase, $pending);
    }

    /**
     * Ask each level once, for the whole test set at a time.
     *
     * Level configs are cumulative (`config.levelN.neon` includes level N-1),
     * so the first level that reports a message is the one that owns it and
     * later levels can be ignored for that message.
     *
     * @return array<string, int> "file\tline\tmessage" => lowest level
     */
    private function buildLevelIndex(): array
    {
        $index = [];

        for ($level = 0; $level < self::MAX_LEVEL; $level++) {
            $command = sprintf(
                '%s analyse -c %s --level=%d --no-progress --error-format=raw %s 2>&1',
                escapeshellarg($this->binaryPath),
                escapeshellarg($this->configPath),
                $level,
                escapeshellarg($this->testsDir),
            );

            $output = [];
            exec($command, $output, $exitCode);

            if ($exitCode !== 0 && $exitCode !== 1) {
                throw new RuntimeException(sprintf('PHPStan invocation failed at level %d', $level));
            }

            foreach ($output as $line) {
                if (preg_match('~^(?<path>\S.*\.php):(?<line>\d+):(?<message>.*)$~', trim($line), $matches) !== 1) {
                    continue;
                }

                $key = basename($matches['path']) . "\t" . $this->messageKey((int) $matches['line'], trim($matches['message']));
                $index[$key] ??= $level;
            }
        }

        return $index;
    }

    /**
     * The per-test walk, for messages the shared index cannot account for.
     *
     * @param array<string, true> $pending
     * @return array<string, int>
     */
    private function walkLevels(TestCase $testCase, array $pending): array
    {
        $levels = [];

        for ($level = 0; $level < self::MAX_LEVEL && $pending !== []; $level++) {
            foreach ($this->runAnalysis($testCase, (string) $level) as $lineNumber => $messages) {
                foreach ($messages as $message) {
                    $key = $this->messageKey($lineNumber, $message);
                    if (!isset($pending[$key])) {
                        continue;
                    }

                    $levels[$key] = $level;
                    unset($pending[$key]);
                }
            }
        }

        // Whatever is still pending is only reported by the top level.
        foreach ($pending as $key => $_) {
            $levels[$key] = self::MAX_LEVEL;
        }

        return $levels;
    }

    /**
     * @param array<int, list<string>> $diagnostics
     * @param array<string, int> $levels
     * @return array<int, int>
     */
    private function lowestLevelPerLine(array $diagnostics, array $levels): array
    {
        $lineLevels = [];

        foreach ($diagnostics as $lineNumber => $messages) {
            foreach ($messages as $message) {
                $level = $levels[$this->messageKey($lineNumber, $message)] ?? null;
                if ($level === null) {
                    continue;
                }

                $lineLevels[$lineNumber] = min($lineLevels[$lineNumber] ?? $level, $level);
            }
        }

        ksort($lineLevels);

        return $lineLevels;
    }

    /**
     * @param array<int, list<string>> $diagnostics
     * @param array<string, int> $levels
     * @return array<int, list<string>>
     */
    private function annotateLevels(array $diagnostics, array $levels): array
    {
        $annotated = [];

        foreach ($diagnostics as $lineNumber => $messages) {
            $annotated[$lineNumber] = [];
            foreach ($messages as $message) {
                $level = $levels[$this->messageKey($lineNumber, $message)] ?? null;
                $annotated[$lineNumber][] = $level === null
                    ? $message
                    : sprintf('%s [reported-from-level=%d]', $message, $level);
            }
        }

        return $annotated;
    }

    private function messageKey(int $lineNumber, string $message): string
    {
        return $lineNumber . "\t" . $message;
    }

    /**
     * @param list<string> $output
     * @return array<int, list<string>>
     */
    private function parseOutput(TestCase $testCase, array $output): array
    {
        $diagnostics = [];

        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $prefix = $testCase->path . ':';
            if (!str_starts_with($line, $prefix)) {
                continue;
            }

            $rest = substr($line, strlen($prefix));
            if ($rest === false) {
                continue;
            }

            [$lineNumberText, $message] = explode(':', $rest, 2) + [null, null];
            if ($lineNumberText === null || $message === null) {
                continue;
            }

            $lineNumber = (int) trim($lineNumberText);
            $diagnostics[$lineNumber] ??= [];
            $diagnostics[$lineNumber][] = trim($message);
        }

        ksort($diagnostics);

        return $diagnostics;
    }

    /**
     * @return array<int, list<string>>
     */
    private function runAnalysis(TestCase $testCase, string $level): array
    {
        $paths = array_map(
            static fn (string $path): string => escapeshellarg($path),
            [...$testCase->supportPaths, $testCase->path],
        );

        $command = sprintf(
            '%s analyse -c %s --level=%s --no-progress --error-format=raw %s 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->configPath),
            escapeshellarg($level),
            implode(' ', $paths),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('PHPStan invocation failed for %s', $testCase->fileName));
        }

        return $this->parseOutput($testCase, $output);
    }
}
