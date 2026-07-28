<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

final class MirChecker implements Checker
{
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

        $command = sprintf(
            '%s --no-progress --no-cache --php-version 8.5 %s 2>&1',
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
