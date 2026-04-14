<?php

declare(strict_types=1);

namespace Conformance\Expectation;

final class ExpectationEvaluator
{
    /**
     * @param list<ExpectedDiagnostic> $expectedDiagnostics
     * @param array<int, list<string>> $actualDiagnostics
     */
    public function evaluate(array $expectedDiagnostics, array $actualDiagnostics, string $toolName): ExpectationEvaluation
    {
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

        foreach ($actualDiagnostics as $line => $messages) {
            if (isset($requiredByLine[$line]) || isset($optionalByLine[$line]) || isset($groupLines[$line])) {
                continue;
            }

            $differences[] = sprintf(
                'Line %d: Unexpected errors %s',
                $line,
                json_encode($messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }

        $errorsDiff = implode("\n", $differences);

        return new ExpectationEvaluation(
            errorsDiff: $errorsDiff,
            conformanceAutomated: $errorsDiff === '' ? 'Pass' : 'Fail',
        );
    }
}
