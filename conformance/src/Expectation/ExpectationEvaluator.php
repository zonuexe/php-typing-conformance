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

        // Report probes (`// E`): success is a diagnostic.
        $requiredByLine = [];
        $optionalByLine = [];
        // Quiet probes (`// Q`): success is silence (suppress/ignore tags).
        $requiredQuietByLine = [];
        $optionalQuietByLine = [];
        // Valid-control probes (`// V`): silence is required; a type-rejection
        // means enforcement on the `// E` lines is incidental.
        $validByLine = [];
        $groups = [];
        // Lines marked `// E[noise]` / `// E?[noise]` may report incidental
        // diagnostics (e.g. "expression has no effect") without counting as
        // enforcement probes for `// T` type-handling rows.
        $noiseLines = [];

        foreach ($expectedDiagnostics as $diagnostic) {
            // psalm-next is a second Psalm installation, not a tool of its
            // own: expectations written for psalm apply to the next line
            // too, and where the 7.x line diverges that shows up as a
            // missed expectation or an unexpected diagnostic — a real
            // finding — rather than as a marker the column never saw.
            $inheritedTool = $toolName === 'psalm-next' && $diagnostic->tool === 'psalm';
            if ($diagnostic->tool !== null && $diagnostic->tool !== $toolName && !$inheritedTool) {
                continue;
            }

            if ($diagnostic->valid) {
                $validByLine[$diagnostic->line] = true;
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

            if ($diagnostic->quiet) {
                if ($diagnostic->required) {
                    $requiredQuietByLine[$diagnostic->line] = true;
                } else {
                    $optionalQuietByLine[$diagnostic->line] = true;
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

        foreach (array_keys($requiredQuietByLine) as $line) {
            $messages = $actualDiagnostics[$line] ?? [];
            if ($messages !== [] && $this->hasEnforcementSignal($messages)) {
                $differences[] = sprintf(
                    'Line %d: Expected silence (quiet probe), got %s',
                    $line,
                    json_encode($messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                );
            }
        }

        foreach (array_keys($validByLine) as $line) {
            $messages = $actualDiagnostics[$line] ?? [];
            if ($messages !== [] && $this->hasEnforcementSignal($messages)) {
                $differences[] = sprintf(
                    'Line %d: Expected valid value to be accepted, got %s',
                    $line,
                    json_encode($messages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                );
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
            if (
                isset($requiredByLine[$line])
                || isset($optionalByLine[$line])
                || isset($requiredQuietByLine[$line])
                || isset($optionalQuietByLine[$line])
                || isset($validByLine[$line])
                || isset($groupLines[$line])
                || isset($noiseLines[$line])
            ) {
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
        // Report probes: success = diagnostic. Quiet probes: success = silence.
        $reportProbes = array_keys($requiredByLine + $optionalByLine);
        $quietProbes = array_keys($requiredQuietByLine + $optionalQuietByLine);

        return new ExpectationEvaluation(
            errorsDiff: $errorsDiff,
            conformanceAutomated: $errorsDiff === '' ? 'Pass' : 'Fail',
            typeHandling: $typeMarkers === []
                ? null
                : $this->typeHandling(
                    $markedLines,
                    $reportProbes,
                    $quietProbes,
                    array_keys($validByLine),
                    $groupProbes,
                    $actualDiagnostics,
                    $falsePositiveLines,
                ),
        );
    }

    /**
     * @param array<int, true> $markedLines
     * @param list<int> $reportProbes untagged // E lines (success = diagnostic)
     * @param list<int> $quietProbes untagged // Q lines (success = silence)
     * @param list<int> $validProbes // V lines (success = silence; type-rejection is over-rejection)
     * @param list<array{tag: string, lines: list<int>}> $groupProbes
     * @param array<int, list<string>> $actualDiagnostics
     * @param list<int> $falsePositiveLines
     */
    private function typeHandling(
        array $markedLines,
        array $reportProbes,
        array $quietProbes,
        array $validProbes,
        array $groupProbes,
        array $actualDiagnostics,
        array $falsePositiveLines,
    ): TypeHandling {
        $unrecognizedLines = [];
        foreach (array_keys($markedLines) as $line) {
            $messages = $actualDiagnostics[$line] ?? [];
            if ($this->hasRecognitionFailure($messages)) {
                $unrecognizedLines[] = $line;
            }
        }
        sort($unrecognizedLines);

        // Report probes: a real diagnostic about the feature counts.
        // Quiet probes (ignore/suppress tags): silence counts; a real
        // diagnostic means the tag was not applied.
        // Incidental "undefined function" noise never counts either way.
        $enforcedLineCount = 0;
        foreach ($reportProbes as $line) {
            $messages = $actualDiagnostics[$line] ?? [];
            if ($messages !== [] && $this->hasEnforcementSignal($messages)) {
                $enforcedLineCount++;
            }
        }
        foreach ($quietProbes as $line) {
            $messages = $actualDiagnostics[$line] ?? [];
            if ($messages === [] || !$this->hasEnforcementSignal($messages)) {
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

        $expectedLineCount = count($reportProbes) + count($quietProbes) + count($groupProbes);
        $enforcement = match (true) {
            $expectedLineCount === 0 => TypeHandling::NO_PROBES,
            $enforcedLineCount === 0 => TypeHandling::NONE,
            $enforcedLineCount === $expectedLineCount => TypeHandling::ENFORCED,
            default => TypeHandling::PARTIAL,
        };

        $overRejectedLines = [];
        foreach ($validProbes as $line) {
            $messages = $actualDiagnostics[$line] ?? [];
            if ($this->hasTypeRejection($messages)) {
                $overRejectedLines[] = $line;
            }
        }
        foreach ($falsePositiveLines as $line) {
            $messages = $actualDiagnostics[$line] ?? [];
            if ($this->hasTypeRejection($messages)) {
                $overRejectedLines[] = $line;
            }
        }
        $overRejectedLines = array_values(array_unique($overRejectedLines));
        sort($overRejectedLines);

        return new TypeHandling(
            recognition: $unrecognizedLines === [] ? TypeHandling::RECOGNIZED : TypeHandling::UNRECOGNIZED,
            enforcement: $enforcement,
            unrecognizedLines: $unrecognizedLines,
            falsePositiveLines: $falsePositiveLines,
            expectedLineCount: $expectedLineCount,
            enforcedLineCount: $enforcedLineCount,
            overRejectedLines: $overRejectedLines,
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
     * A diagnostic that actually rejects a value or return as the wrong type —
     * as opposed to unused-variable lint, missing typehints, or helper noise.
     * Used to tell "rejected a valid control" from unrelated false positives.
     *
     * @param list<string> $messages
     */
    private function hasTypeRejection(array $messages): bool
    {
        foreach ($messages as $message) {
            if ($this->isTypeRejection($message)) {
                return true;
            }
        }

        return false;
    }

    private function isTypeRejection(string $message): bool
    {
        if ($this->isIncidentalDiagnostic($message) || $this->isNonRecognitionDiagnostic($message)) {
            return false;
        }

        if (preg_match(
            '/\[(argument\.type|return\.type|parameter\.type|offsetAccess\.|MIR0201|MIR0202|PhanTypeMismatch\w*|type_mismatch_argument|type_mismatch_return|invalid-argument|invalid-return-statement|PhpParamsInspection|PhpArrayKeyDoesNotMatchArrayShapeInspection|PhpMissingArrayKeyInspection|P1006|P1012)\]/',
            $message,
        ) === 1) {
            return true;
        }

        return preg_match(
            '/\b(expects .+ given|but (?:.* )?given|but found|should return|InvalidArgument|Invalid (?:argument|return) type|TypeMismatch|incompatible with|does not (?:accept|match)|got [\'"`]|Value should be one of|Missing key|Incomplete array according to shape|nothing in common)\b/i',
            $message,
        ) === 1;
    }

    /**
     * @param list<string> $messages
     */
    private function hasRecognitionFailure(array $messages): bool
    {
        $sawAny = false;
        foreach ($messages as $message) {
            $sawAny = true;
            if ($this->isRecognitionFailure($message)) {
                return true;
            }
        }

        // No messages, or only non-resolution noise → recognized.
        return false;
    }

    /**
     * A diagnostic on a `// T` line that means the spelling was not resolved.
     * Style / documented-vs-declared complaints are not recognition failures:
     * those fire only after the type was parsed.
     */
    private function isRecognitionFailure(string $message): bool
    {
        if ($this->isNonRecognitionDiagnostic($message) || $this->isIncidentalDiagnostic($message)) {
            return false;
        }

        return true;
    }

    /**
     * Diagnostics that land on a declaration without meaning "I do not know
     * this spelling": documented-vs-native mismatch after a successful parse,
     * synonym style nits, unused / missing-typehint lint.
     */
    private function isNonRecognitionDiagnostic(string $message): bool
    {
        if (preg_match('/\[(P1131|PhanTypeMismatchDeclaredReturn|PhanTemplateTypeNotUsedInFunctionReturn)\]/', $message) === 1) {
            return true;
        }

        if (preg_match('/\bDocumented type is not compatible with the declared type\b/i', $message) === 1) {
            return true;
        }

        if (preg_match('/\bUse \w+ type instead of\b/i', $message) === 1) {
            return true;
        }

        if (preg_match(
            '/\b(missingType\.(?:parameter|return|property)|unused (?:parameter|variable)|never (?:read|used)|not used)\b/i',
            $message,
        ) === 1) {
            return true;
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
