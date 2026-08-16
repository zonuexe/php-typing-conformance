<?php

declare(strict_types=1);

namespace Conformance\Checker;

use Conformance\Discovery\TestCase;
use RuntimeException;

/**
 * Adapter for Psalm (https://psalm.dev/), used for both the released line and
 * the `psalm-next` column that tracks the next major.
 *
 * The corpus is analysed in a single whole-project run rather than one
 * invocation per test file. That is not an optimisation bolted on afterwards:
 * `psalm.xml` declares `<projectFiles><directory name="tests"/></projectFiles>`,
 * so a per-file invocation already scanned all 218 files and then threw away
 * every verdict but one. Asking once and slicing the answer costs a second
 * instead of two and a half minutes.
 *
 * The one thing that changes is which issues can fire at all. A whole-project
 * run knows facts no single file can — that a class is never extended, that a
 * property is never written — so Psalm reports architecture and annotation nags
 * that per-file mode structurally could not. Those are suppressed in the config
 * rather than absorbed here; see the `<issueHandlers>` comments in
 * `psalm.xml` / `psalm-next.xml` for which ones and why. With them suppressed
 * the batch verdict is byte-identical to the per-file verdict it replaces.
 *
 * Support files need no special handling: they live in `tests/` alongside the
 * cases that use them, so the project run has already read them.
 */
final class PsalmChecker implements Checker
{
    /**
     * Diagnostics for the whole corpus, keyed by absolute file path, or null
     * until the first {@see analyse()} asks for them.
     *
     * @var array<string, array<int, list<string>>>|null
     */
    private ?array $report = null;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $binaryPath,
        private readonly string $configPath,
        private readonly string $toolName = 'psalm',
    ) {
    }

    public function name(): string
    {
        return $this->toolName;
    }

    public function version(): string
    {
        $command = escapeshellarg($this->binaryPath) . ' --version';
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Failed to determine Psalm version.');
        }

        return trim(implode("\n", $output));
    }

    /**
     * @return array<int, list<string>>
     */
    public function analyse(TestCase $testCase): array
    {
        return $this->report()[$testCase->path] ?? [];
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    private function report(): array
    {
        if ($this->report !== null) {
            return $this->report;
        }

        // No paths: the config's projectFiles is the corpus. stderr is kept off
        // stdout because it would otherwise land in the middle of the JSON.
        $command = sprintf(
            '%s --config=%s --no-progress --output-format=json 2>/dev/null',
            escapeshellarg($this->binaryPath),
            escapeshellarg($this->configPath),
        );

        exec($command, $output, $exitCode);

        // 0 = clean, 2 = issues found. Anything else is a failed invocation,
        // and because this is one run for the whole column, a failure here
        // takes down every test rather than one.
        if ($exitCode !== 0 && $exitCode !== 2) {
            throw new RuntimeException(sprintf('%s invocation failed (exit %d)', $this->toolName, $exitCode));
        }

        return $this->report = $this->parseOutput(implode("\n", $output));
    }

    /**
     * @return array<string, array<int, list<string>>>
     */
    private function parseOutput(string $output): array
    {
        /** @var mixed $data */
        $data = json_decode($output, true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Failed to parse %s output', $this->toolName));
        }

        $issues = array_is_list($data) ? $data : ($data['issues'] ?? []);
        if (!is_array($issues)) {
            return [];
        }

        $byFile = [];

        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }

            $filePath = (string) ($issue['file_path'] ?? '');
            $lineNumber = (int) ($issue['line_from'] ?? 0);
            $message = trim((string) ($issue['message'] ?? ''));
            $type = trim((string) ($issue['type'] ?? ''));

            if ($filePath === '' || $lineNumber <= 0 || $message === '') {
                continue;
            }

            $formatted = $type !== '' ? sprintf('%s [%s]', $message, $type) : $message;
            $byFile[$filePath][$lineNumber] ??= [];
            $byFile[$filePath][$lineNumber][] = $formatted;
        }

        foreach ($byFile as &$diagnostics) {
            ksort($diagnostics);
        }

        return $byFile;
    }
}
