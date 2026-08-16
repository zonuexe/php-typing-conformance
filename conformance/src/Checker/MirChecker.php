<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for mir (https://github.com/miropen/mir-php).
 *
 * mir puts several judgements at `info` severity that its peers report as
 * ordinary findings, and `--show-info` is all-or-nothing. This adapter used to
 * ask for info only on `debug_*` files, where `@trace` needs it, and that
 * quietly cost the column its most important signal: `UndefinedDocblockClass`
 * (MIR1505) — mir's answer to "I do not know this spelling" — is info, so it
 * never reached the evaluator. Twenty-one tests recorded mir as having
 * *recognized* a type it had in fact rejected, among them most of the
 * `phpdoc_advanced_fallback_*` shelf, whose entire subject is which spellings
 * an analyzer knows. Reading silence as understanding is only sound when the
 * tool was allowed to speak.
 *
 * So info is on everywhere and the noise is dropped by code instead, the way
 * IntelephenseChecker drops P1003. Only `UnusedParam` qualifies: every other
 * info code carries a judgement whose
 * equivalent this suite already records from another column — mixed-type
 * complaints from Mago, `PossiblyInvalidArgument` and `RedundantCondition`
 * and `MissingConstructor` from Psalm, deprecation from Intelephense.
 */
final class MirChecker implements Checker
{
    /**
     * Diagnostic codes dropped as non-type-conformance noise.
     *
     * - MIR0501 `UnusedParam` — these fixtures exist to exercise a signature,
     *   not to consume every parameter, which is the rationale
     *   IntelephenseChecker documents for dropping its P1003.
     * - MIR1102 `MissingThrowsDocblock` — every occurrence in this corpus is
     *   `Random\RandomException` from a `random_int()` call used to produce a
     *   value the analyzer cannot fold, on a line that is about something
     *   else, and mir is the only tool that checks `@throws` completeness at
     *   all. It never fires on `exceptions_throws_docblock`, the test that
     *   would actually want the answer — mir is silent there — so nothing is
     *   lost today. Revisit this entry if that ever changes.
     *
     * @var list<string>
     */
    private const IGNORED_CODES = ['MIR0501', 'MIR1102'];

    private ?string $anchorPath = null;

    public function __construct(
        private readonly string $binaryPath,
    ) {
    }

    public function name(): string
    {
        return 'mir';
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' --version';
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine mir version.');
        }

        return trim($this->stripAnsi(implode("\n", $output)));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        // mir resolves a Composer root by walking up from a single input path
        // and hard-exits (code 2) when that composer.json lacks an `autoload`
        // section, which is the case for this repository. Passing two or more
        // paths forces mir into its plain-flow (bundled stubs, no Composer
        // discovery), so we always prepend a neutral anchor file. Diagnostics
        // from the anchor and support files are filtered out below.
        $paths = array_map(
            static fn (string $path): string => escapeshellarg($path),
            [$this->anchorPath(), ...$testCase->supportPaths, $testCase->path],
        );

        // Info severity carries real findings here, not just `@trace`
        // (MIR0221); see the class docblock. IGNORED_CODES drops the noise.
        $command = sprintf(
            '%s --no-progress --no-cache --php-version 8.5 --show-info %s 2>&1',
            escapeshellarg($this->binaryPath),
            implode(' ', $paths),
        );

        exec($command, $output, $exitCode);

        // 0 = no issues, 1 = issues found. Anything else is a real failure.
        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new RuntimeException(sprintf('mir invocation failed for %s', $testCase->fileName));
        }

        return $this->parseOutput($testCase, implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    private function parseOutput(TestCase $testCase, string $output): array
    {
        $diagnostics = [];

        foreach (explode("\n", $this->stripAnsi($output)) as $line) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            // Format: `<file>:<line>:<col> <severity>[<code>] <name>: <message>`
            if (!preg_match(
                '/^(?<file>.+):(?<line>\d+):(?<col>\d+)\s+(?<sev>error|warning|info)\[(?<code>[^\]]+)\]\s+(?<body>.+)$/',
                $line,
                $matches,
            )) {
                continue;
            }

            if ($matches['file'] !== $testCase->path) {
                continue;
            }

            $lineNumber = (int) $matches['line'];
            if ($lineNumber <= 0) {
                continue;
            }

            if (in_array($matches['code'], self::IGNORED_CODES, true)) {
                continue;
            }

            $body = trim($matches['body']);
            $formatted = sprintf('%s [%s]', $body, $matches['code']);

            $diagnostics[$lineNumber] ??= [];
            $diagnostics[$lineNumber][] = $formatted;
        }

        ksort($diagnostics);

        return $diagnostics;
    }

    private function stripAnsi(string $text): string
    {
        return (string) preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    }

    private function anchorPath(): string
    {
        if ($this->anchorPath !== null) {
            return $this->anchorPath;
        }

        $path = sys_get_temp_dir() . '/mir-conformance-anchor.php';
        if (!is_file($path)) {
            file_put_contents($path, "<?php\n\ndeclare(strict_types=1);\n");
        }

        return $this->anchorPath = $path;
    }
}
