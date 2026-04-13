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
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Test file not found: %s', $path));
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException(sprintf('Failed to read test file: %s', $path));
        }

        $diagnostics = [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            if (!preg_match_all('/\/\/\s*E(\?)?(?:\[([^\]]+)\])?(?::\s*(.*))?$/', $line, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $tag = $match[2] ?? null;
                $allowMultiple = false;

                if (is_string($tag) && str_ends_with($tag, '+')) {
                    $allowMultiple = true;
                    $tag = substr($tag, 0, -1);
                }

                $diagnostics[] = new ExpectedDiagnostic(
                    line: $lineNumber,
                    required: ($match[1] ?? '') !== '?',
                    tag: $tag !== '' ? $tag : null,
                    allowMultiple: $allowMultiple,
                    comment: trim((string) ($match[3] ?? '')),
                );
            }
        }

        return $diagnostics;
    }
}
