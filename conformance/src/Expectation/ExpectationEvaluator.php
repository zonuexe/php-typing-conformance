<?php

declare(strict_types=1);

namespace Conformance\Expectation;

final class ExpectationEvaluator
{
    /**
     * @param list<ExpectedDiagnostic> $expectedDiagnostics
     * @param array<int, list<string>> $actualDiagnostics
     * @param list<TypeMarker> $typeMarkers lines whose PHPDoc spelling is under
     *        test; a diagnostic there means the spelling was not recognized, so
     *        it is legitimate output rather than an unexpected error
     */
    public function evaluate(
        array $expectedDiagnostics,
        array $actualDiagnostics,
        string $toolName,
        array $typeMarkers = [],
    ): ExpectationEvaluation {
        $markedLines = [];
        foreach ($typeMarkers as $marker) {
            $markedLines[$marker->line] = true;
        }

        $requiredByLine = [];
        $optionalByLine = [];
        $groups = [];

        foreach ($expectedDiagnostics as $diagnostic) {
            if ($diagnostic->tool !== null && $diagnostic->tool !== $toolName) {
                continue;
            }

            if ($diagnostic->tag !== null) {
                $groups[$diagnostic->tag] ??= [
                    'lines' => [],
                    'allow_multiple' => $diagnostic->allowMultiple,
                ];
                $groups[$diagnostic->tag]['lines'][] = $diagnostic->line;
                continue;
            }

            if ($diagnostic->required) {
                $requiredByLine[$diagnostic->line] = ($requiredByLine[$diagnostic->line] ?? 0) + 1;
            } else {
                $optionalByLine[$diagnostic->line] = ($optionalByLine[$diagnostic->line] ?? 0) + 1;
            }
        }

        $differences = [];
        $groupLines = [];

        foreach ($requiredByLine as $line => $count) {
            if (!isset($actualDiagnostics[$line])) {
                $differences[] = sprintf('Line %d: Expected %d error(s)', $line, $count);
            }
        }

        foreach ($groups as $tag => $group) {
            $lines = $group['lines'];
            sort($lines);
            $numErrors = 0;
            foreach ($lines as $line) {
                if (isset($actualDiagnostics[$line])) {
                    $numErrors++;
                }
                $groupLines[$line] = true;
            }

            if ($numErrors === 0) {
                $differences[] = sprintf(
                    'Lines %s: Expected error (tag %s)',
                    implode(', ', $lines),
                    $tag,
                );
            } elseif ($numErrors > 1 && !$group['allow_multiple']) {
                $differences[] = sprintf(
                    'Lines %s: Expected exactly one error (tag %s)',
                    implode(', ', $lines),
                    $tag,
                );
            }
        }

        $falsePositiveLines = [];

        foreach ($actualDiagnostics as $line => $messages) {
            if (isset($requiredByLine[$line]) || isset($optionalByLine[$line]) || isset($groupLines[$line])) {
                continue;
            }

            if (isset($markedLines[$line])) {
                continue;
            }

            $falsePositiveLines[] = $line;
            $differences[] = sprintf(
                'Line %d: Unexpected errors %s',
                $line,
                json_encode($messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }

        $errorsDiff = implode("\n", $differences);
        $expectedLines = array_keys($requiredByLine + $optionalByLine + $groupLines);

        return new ExpectationEvaluation(
            errorsDiff: $errorsDiff,
            conformanceAutomated: $errorsDiff === '' ? 'Pass' : 'Fail',
            typeHandling: $typeMarkers === []
                ? null
                : $this->typeHandling($markedLines, $expectedLines, $actualDiagnostics, $falsePositiveLines),
        );
    }

    /**
     * @param array<int, true> $markedLines
     * @param list<int> $expectedLines
     * @param array<int, list<string>> $actualDiagnostics
     * @param list<int> $falsePositiveLines
     */
    private function typeHandling(
        array $markedLines,
        array $expectedLines,
        array $actualDiagnostics,
        array $falsePositiveLines,
    ): TypeHandling {
        $unrecognizedLines = [];
        foreach (array_keys($markedLines) as $line) {
            if (isset($actualDiagnostics[$line])) {
                $unrecognizedLines[] = $line;
            }
        }
        sort($unrecognizedLines);

        $enforcedLineCount = 0;
        foreach ($expectedLines as $line) {
            if (isset($actualDiagnostics[$line])) {
                $enforcedLineCount++;
            }
        }

        $expectedLineCount = count($expectedLines);
        $enforcement = match (true) {
            $expectedLineCount === 0 => TypeHandling::NO_PROBES,
            $enforcedLineCount === 0 => TypeHandling::NONE,
            $enforcedLineCount === $expectedLineCount => TypeHandling::ENFORCED,
            default => TypeHandling::PARTIAL,
        };

        return new TypeHandling(
            recognition: $unrecognizedLines === [] ? TypeHandling::RECOGNIZED : TypeHandling::UNRECOGNIZED,
            enforcement: $enforcement,
            unrecognizedLines: $unrecognizedLines,
            falsePositiveLines: $falsePositiveLines,
            expectedLineCount: $expectedLineCount,
            enforcedLineCount: $enforcedLineCount,
        );
    }
}
