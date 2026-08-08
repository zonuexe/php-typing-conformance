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
        // Lines marked `// E[noise]` / `// E?[noise]` may report incidental
        // diagnostics (e.g. "expression has no effect") without counting as
        // enforcement probes for `// T` type-handling rows.
        $noiseLines = [];

        foreach ($expectedDiagnostics as $diagnostic) {
            if ($diagnostic->tool !== null && $diagnostic->tool !== $toolName) {
                continue;
            }

            if ($diagnostic->tag === 'noise') {
                $noiseLines[$diagnostic->line] = true;
                continue;
            }

            if ($diagnostic->tag !== null) {
                $groups[$diagnostic->tag] ??= [
                    'lines' => [],
                    'allow_multiple' => $diagnostic->allowMultiple,
                    // A group is required only if at least one of its markers is.
                    // `// E?[tag]` alone means "may fire on any of these lines".
                    'required' => false,
                ];
                $groups[$diagnostic->tag]['lines'][] = $diagnostic->line;
                $groups[$diagnostic->tag]['allow_multiple'] = $groups[$diagnostic->tag]['allow_multiple']
                    || $diagnostic->allowMultiple;
                if ($diagnostic->required) {
                    $groups[$diagnostic->tag]['required'] = true;
                }
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
        /** @var list<array{tag: string, lines: list<int>}> $groupProbes */
        $groupProbes = [];

        foreach ($requiredByLine as $line => $count) {
            if (!isset($actualDiagnostics[$line])) {
                $differences[] = sprintf('Line %d: Expected %d error(s)', $line, $count);
            }
        }

        foreach ($groups as $tag => $group) {
            $lines = array_values(array_unique($group['lines']));
            sort($lines);
            $numErrors = 0;
            foreach ($lines as $line) {
                if (isset($actualDiagnostics[$line])) {
                    $numErrors++;
                }
                $groupLines[$line] = true;
            }
            // @trace / @psalm-trace: tools disagree on which line to blame, and
            // the docblock line cannot carry `// E` without a trailing comment
            // that makes mir drop the annotation. Count a type-trace diagnostic
            // anywhere in the file as satisfying the group for Pass/Fail.
            if ($tag === 'trace' && $numErrors === 0 && $this->fileHasTypeTraceSignal($actualDiagnostics)) {
                $numErrors = 1;
            }
            $groupProbes[] = ['tag' => $tag, 'lines' => $lines];

            if ($numErrors === 0) {
                if ($group['required']) {
                    $differences[] = sprintf(
                        'Lines %s: Expected error (tag %s)',
                        implode(', ', $lines),
                        $tag,
                    );
                }
            } elseif ($numErrors > 1 && !$group['allow_multiple'] && $tag !== 'trace') {
                $differences[] = sprintf(
                    'Lines %s: Expected exactly one error (tag %s)',
                    implode(', ', $lines),
                    $tag,
                );
            }
        }

        $falsePositiveLines = [];
        $traceSpellingUnderTest = $this->hasTraceSpellingMarker($typeMarkers);

        foreach ($actualDiagnostics as $line => $messages) {
            if (isset($requiredByLine[$line]) || isset($optionalByLine[$line]) || isset($groupLines[$line]) || isset($noiseLines[$line])) {
                continue;
            }

            if (isset($markedLines[$line])) {
                continue;
            }

            // Type-trace dumps for the spelling under test may land on the
            // docblock line (Mago, Steins) rather than the // E?[trace] anchor.
            if ($traceSpellingUnderTest && $this->hasTypeTraceSignal($messages)) {
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
        // Per-line probes (untagged // E) plus one probe per // E[tag] group.
        $lineProbes = array_keys($requiredByLine + $optionalByLine);

        return new ExpectationEvaluation(
            errorsDiff: $errorsDiff,
            conformanceAutomated: $errorsDiff === '' ? 'Pass' : 'Fail',
            typeHandling: $typeMarkers === []
                ? null
                : $this->typeHandling($markedLines, $lineProbes, $groupProbes, $actualDiagnostics, $falsePositiveLines),
        );
    }

    /**
     * @param array<int, true> $markedLines
     * @param list<int> $lineProbes untagged expected lines
     * @param list<array{tag: string, lines: list<int>}> $groupProbes
     * @param array<int, list<string>> $actualDiagnostics
     * @param list<int> $falsePositiveLines
     */
    private function typeHandling(
        array $markedLines,
        array $lineProbes,
        array $groupProbes,
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

        // A diagnostic only counts as enforcement when it is about the feature
        // under test. "Function PHPStan\dumpType does not exist" and similar
        // mean the analyzer does *not* implement the helper.
        $enforcedLineCount = 0;
        foreach ($lineProbes as $line) {
            $messages = $actualDiagnostics[$line] ?? [];
            if ($messages !== [] && $this->hasEnforcementSignal($messages)) {
                $enforcedLineCount++;
            }
        }
        foreach ($groupProbes as $group) {
            $hit = false;
            foreach ($group['lines'] as $line) {
                $messages = $actualDiagnostics[$line] ?? [];
                if ($messages !== [] && $this->hasEnforcementSignal($messages)) {
                    $hit = true;
                    break;
                }
            }
            // Same attribution flexibility as Pass/Fail for @trace groups.
            if (!$hit && $group['tag'] === 'trace' && $this->fileHasTypeTraceSignal($actualDiagnostics)) {
                $hit = true;
            }
            if ($hit) {
                $enforcedLineCount++;
            }
        }

        $expectedLineCount = count($lineProbes) + count($groupProbes);
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

    /**
     * @param list<\Conformance\Expectation\TypeMarker> $typeMarkers
     */
    private function hasTraceSpellingMarker(array $typeMarkers): bool
    {
        foreach ($typeMarkers as $marker) {
            if (preg_match('/@?(psalm-)?trace\b/i', $marker->spelling) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, list<string>> $actualDiagnostics
     */
    private function fileHasTypeTraceSignal(array $actualDiagnostics): bool
    {
        foreach ($actualDiagnostics as $messages) {
            if ($this->hasTypeTraceSignal($messages)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $messages
     */
    private function hasTypeTraceSignal(array $messages): bool
    {
        foreach ($messages as $message) {
            if ($this->isTypeTraceDiagnostic($message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A successful dump of an inferred type from @trace / @psalm-trace (not an
     * undefined-function incidental).
     */
    private function isTypeTraceDiagnostic(string $message): bool
    {
        if (preg_match('/\[(Trace|MIR0221|psalm-trace|debug\.trace)\]/', $message) === 1) {
            return true;
        }

        if (preg_match('/\bTrace:\s*Type of\b/i', $message) === 1) {
            return true;
        }

        if (preg_match('/\btraced type of\b/i', $message) === 1) {
            return true;
        }

        // Psalm: "$value: int [Trace]" already matched by [Trace]; bare form:
        if (preg_match('/^\$\w+:\s+.+\s+\[Trace\]/', $message) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param list<string> $messages
     */
    private function hasEnforcementSignal(array $messages): bool
    {
        foreach ($messages as $message) {
            if (!$this->isIncidentalDiagnostic($message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Diagnostics that appear on a probe line without meaning the analyzer
     * honoured the feature:
     *
     * - unknown **analyzer pseudo-APIs** (`PHPStan\dumpType`, `Mago\inspect`,
     *   `PHPStan\TrinaryLogic`, …) reported as undefined function/class;
     * - no-op expression lint on string annotations such as `@phan-debug-var`.
     *
     * Deliberately narrow: a real type probe may itself be "function X is not
     * defined" (e.g. `callable-string` rejecting `definitely_not_a_function`).
     * Only missing **tool helpers** are incidental.
     */
    private function isIncidentalDiagnostic(string $message): bool
    {
        if (preg_match(
            '/\b(does not do anything|has no effect as a statement|has no effect|evaluated but not used|unused-statement|discardexpr)\b/i',
            $message,
        ) === 1) {
            return true;
        }

        // IDE / Qodana: "Undefined namespace 'Testing'" / "Undefined class
        // 'TrinaryLogic'" — short names without the PHPStan\ prefix.
        if (preg_match('/\bundefined namespace\b/i', $message) === 1
            || preg_match('/\[PhpUndefinedNamespaceInspection\]/', $message) === 1) {
            return true;
        }

        if (preg_match('/\bTrinaryLogic\b/', $message) === 1
            && preg_match('/\b(undefined|unknown|does not exist|not found|undeclared)\b/i', $message) === 1) {
            return true;
        }

        if (preg_match('/\[PhpUndefinedClassInspection\]/', $message) === 1
            && preg_match('/\b(TrinaryLogic|Testing)\b/', $message) === 1) {
            return true;
        }

        // Pseudo-API names that live in analyzer namespaces, not user code.
        // Messages may use one backslash (PHPStan\dumpType) after TOML round-trip.
        if (preg_match('/(?:PHPStan\\\\?|Mago\\\\?inspect)/', $message) !== 1) {
            return false;
        }

        // Missing symbol of any kind on a pseudo-API path (function, class,
        // type, method on TrinaryLogic, …) is still "I do not know this helper".
        // Includes phpy's "Call to unknown function: 'PHPStan\…'".
        return preg_match(
            '/\b(does not exist|could not be found|not found|is not defined|undeclared function|undeclared class|undefined function|unknown function|use of unknown class|undefined class|undefined type|undefined method|undefinedclass|non-existent-function|non-existent-method|phanundeclaredfunction|phanundeclaredclassmethod)\b/i',
            $message,
        ) === 1
            || preg_match(
                '/\[(UndefinedFunction|UndefinedClass|non-existent-function|non-existent-method|PhanUndeclaredFunction|PhanUndeclaredClassMethod|MIR0003|P1009|P1010)\]/',
                $message,
            ) === 1;
    }
}
