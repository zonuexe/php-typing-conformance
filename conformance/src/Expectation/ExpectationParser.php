<?php

declare(strict_types=1);

namespace Conformance\Expectation;

use RuntimeException;

final class ExpectationParser
{
    /**
     * @return list<ExpectedDiagnostic>
     */
    public function parseFile(string $path): array
    {
        $lines = $this->readLines($path);
        $diagnostics = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            if (!preg_match_all('/\/\/\s*E(\?)?(?:<([^>]+)>)?(?:\[([^\]]+)\])?(?::\s*(.*?))?(?=(?:\s*\/\/\s*E|$))/', $line, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $tool = $match[2] ?? null;
                $tag = $match[3] ?? null;
                $allowMultiple = false;

                if (is_string($tag) && str_ends_with($tag, '+')) {
                    $allowMultiple = true;
                    $tag = substr($tag, 0, -1);
                }

                $diagnostics[] = new ExpectedDiagnostic(
                    line: $lineNumber,
                    required: ($match[1] ?? '') !== '?',
                    tool: $tool !== '' ? $tool : null,
                    tag: $tag !== '' ? $tag : null,
                    allowMultiple: $allowMultiple,
                    comment: trim((string) ($match[4] ?? '')),
                );
            }
        }

        return $diagnostics;
    }

    /**
     * Parse `// T` markers: `// T`, or `// T: int-range<0, 255>` to record the
     * spelling under test.
     *
     * A marker on a declaration also covers the docblock directly above it.
     * Analyzers disagree about where to blame an unresolvable type - PHPStan
     * points at the signature, Phan at the `@param` line it could not parse -
     * and both mean the same thing, so one marker claims the whole declaration
     * rather than making every test carry two.
     *
     * @return list<TypeMarker>
     */
    public function parseTypeMarkers(string $path): array
    {
        $lines = $this->readLines($path);
        $markers = [];

        foreach ($lines as $index => $line) {
            if (preg_match('/\/\/\s*T\b(?::\s*(.*))?$/', $line, $match) !== 1) {
                continue;
            }

            $spelling = trim((string) ($match[1] ?? ''));

            foreach ($this->docblockAbove($lines, $index) as $docblockIndex) {
                $markers[] = new TypeMarker(line: $docblockIndex + 1, spelling: $spelling);
            }

            $markers[] = new TypeMarker(line: $index + 1, spelling: $spelling);
        }

        return $markers;
    }

    /**
     * Zero-based indexes of the docblock ending on the line above $index, or an
     * empty list when there is none.
     *
     * @param list<string> $lines
     * @return list<int>
     */
    private function docblockAbove(array $lines, int $index): array
    {
        $end = $index - 1;
        if ($end < 0 || !str_ends_with(trim($lines[$end]), '*/')) {
            return [];
        }

        for ($start = $end; $start >= 0; $start--) {
            if (str_starts_with(trim($lines[$start]), '/**')) {
                return range($start, $end);
            }

            // A blank line or a statement means this is not a docblock body.
            if ($start !== $end && !str_starts_with(trim($lines[$start]), '*')) {
                return [];
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function readLines(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Test file not found: %s', $path));
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException(sprintf('Failed to read test file: %s', $path));
        }

        return $lines;
    }
}
