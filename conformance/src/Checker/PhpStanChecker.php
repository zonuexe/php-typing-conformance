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
 * 1.2 seconds. Every pass here is therefore run over the whole test set at
 * once -- the authoritative one at max, and one per rung of the level ladder
 * to place each message on it. Twelve invocations for the whole matrix column,
 * where the per-test pass alone used to be 216.
 *
 * Batching the authoritative pass is the part worth justifying, because
 * analysing each test alone is what makes the result a measurement of *that*
 * test. Two mechanisms could break that when the corpus is analysed as one
 * project: rules that count across a project ("Trait X is used zero times"
 * means something else when the project is one file), and cross-file symbol
 * resolution, which is live -- a class referenced but not analysed is a
 * `class.notFound`, and putting its declaration in scope silently replaces
 * that diagnostic with whatever the real type says.
 *
 * Neither fires here, and that was measured rather than assumed: one whole-
 * corpus run per config, diffed line by line against a per-file sweep of all
 * 216 test files, is byte-identical under both `phpstan-no-strict.neon` (303
 * diagnostics) and `phpstan.dist.neon` (307), and matches every stored result.
 * The reason it holds is a property of the corpus, not of PHPStan: each test
 * is one file in its own `Conformance\Tests\<Name>` namespace, its support
 * files are the `_<name>`-prefixed ones that the per-file pass already put in
 * scope, and no test names another test's symbols. A test that broke that
 * isolation would be measured with the other test's declarations in scope, so
 * keep new tests self-contained.
 *
 * The level walk keeps its per-test fallback regardless. There, absence is
 * recoverable -- a message the shared index has never seen can simply be
 * asked about on its own -- which is not true of the authoritative pass, where
 * absence is the answer.
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

    /**
     * The authoritative max-level pass, likewise built once.
     *
     * @var array<string, array<int, list<string>>>|null file name => diagnostics
     */
    private ?array $maxDiagnostics = null;

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

        $this->maxDiagnostics ??= $this->runCorpusAnalysis('max');
        $maxDiagnostics = $this->maxDiagnostics[$testCase->fileName] ?? [];

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
     * The ladder is walked all the way to {@see MAX_LEVEL} and not one rung
     * short of it. A message that the top rung alone reports used to be missed
     * here and then rediscovered by {@see walkLevels}, at ten invocations per
     * test, only to be told the level the fallback would have assumed anyway.
     * Asking the top rung the same way as the others costs one more run of the
     * whole set. It does not make the fallback redundant: `--level=max` is
     * resolved by PHPStan, not here, so a max pass that outruns this ladder
     * still lands in the fallback rather than being silently mislabelled.
     *
     * @return array<string, int> "file\tline\tmessage" => lowest level
     */
    private function buildLevelIndex(): array
    {
        $index = [];

        for ($level = 0; $level <= self::MAX_LEVEL; $level++) {
            foreach ($this->runCorpusAnalysis((string) $level) as $fileName => $diagnostics) {
                foreach ($diagnostics as $lineNumber => $messages) {
                    foreach ($messages as $message) {
                        $key = $fileName . "\t" . $this->messageKey($lineNumber, $message);
                        $index[$key] ??= $level;
                    }
                }
            }
        }

        return $index;
    }

    /**
     * One invocation over the whole test set.
     *
     * @return array<string, array<int, list<string>>> file name => diagnostics
     */
    private function runCorpusAnalysis(string $level): array
    {
        $command = sprintf(
            '%s analyse -c %s --level=%s --no-progress --error-format=raw %s 2>&1',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->configPath),
            escapeshellarg($level),
            escapeshellarg($this->testsDir),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('PHPStan invocation failed at level %s', $level));
        }

        return $this->parseOutput($output);
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
     * The `raw` error format is one `path:line:message` per line, with an
     * advisory header that carries no line number and so falls out here.
     *
     * @param list<string> $output
     * @return array<string, array<int, list<string>>> file name => diagnostics
     */
    private function parseOutput(array $output): array
    {
        $diagnostics = [];

        foreach ($output as $line) {
            if (preg_match('~^(?<path>\S.*\.php):(?<line>\d+):(?<message>.*)$~', trim($line), $matches) !== 1) {
                continue;
            }

            $diagnostics[basename($matches['path'])][(int) $matches['line']][] = trim($matches['message']);
        }

        foreach (array_keys($diagnostics) as $fileName) {
            ksort($diagnostics[$fileName]);
        }

        return $diagnostics;
    }

    /**
     * One invocation for one test, with the support files the corpus run would
     * have supplied. Only the level walk still needs this.
     *
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

        return $this->parseOutput($output)[$testCase->fileName] ?? [];
    }
}
