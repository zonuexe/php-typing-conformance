<?php

declare(strict_types=1);

/**
 * Harness self-test for ExpectationEvaluator classification.
 *
 * Run: php conformance/src/Expectation/self-test.php
 */

use Conformance\Expectation\ExpectationEvaluator;
use Conformance\Expectation\ExpectedDiagnostic;
use Conformance\Expectation\TypeHandling;
use Conformance\Expectation\TypeMarker;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$failures = 0;

function expect(string $label, mixed $actual, mixed $expected): void
{
    global $failures;
    if ($actual !== $expected) {
        $failures++;
        fwrite(STDERR, sprintf(
            "FAIL %s\n  expected %s\n  actual   %s\n",
            $label,
            var_export($expected, true),
            var_export($actual, true),
        ));
        return;
    }

    echo "ok  {$label}\n";
}

$evaluator = new ExpectationEvaluator();

$e = static fn (int $line, bool $required = false, bool $valid = false, bool $quiet = false): ExpectedDiagnostic => new ExpectedDiagnostic(
    line: $line,
    required: $required,
    tool: null,
    tag: null,
    allowMultiple: false,
    comment: '',
    quiet: $quiet,
    valid: $valid,
);

$t = static fn (int $line, string $spelling = 'number'): TypeMarker => new TypeMarker(line: $line, spelling: $spelling);

// Genuine enforcement: invalid rejected, valid silent, declaration clean.
$got = $evaluator->evaluate(
    [$e(20), $e(10, valid: true)],
    [20 => ['Parameter expects int<0,255>, 256 given. [identifier=argument.type]']],
    'phpstan',
    [$t(5)],
);
expect('genuine/verdict', $got->conformanceAutomated, 'Pass');
expect('genuine/recognition', $got->typeHandling?->recognition, TypeHandling::RECOGNIZED);
expect('genuine/enforcement', $got->typeHandling?->enforcement, TypeHandling::ENFORCED);
expect('genuine/incidental', $got->typeHandling?->isIncidental(), false);
expect('genuine/over-rejected', $got->typeHandling?->overRejectedLines, []);

// Over-rejection: valid int also rejected. Pass/Fail is Fail; enforcement
// stays "enforced" on the E line but incidental via over_rejected_lines.
$got = $evaluator->evaluate(
    [$e(20), $e(10, valid: true)],
    [
        10 => ['Argument $value expects number, got 1 [MIR0201]'],
        20 => ['Argument $value expects number, got "1" [MIR0201]'],
    ],
    'mir',
    [$t(5)],
);
expect('over-reject/verdict', $got->conformanceAutomated, 'Fail');
expect('over-reject/recognition', $got->typeHandling?->recognition, TypeHandling::RECOGNIZED);
expect('over-reject/enforcement', $got->typeHandling?->enforcement, TypeHandling::ENFORCED);
expect('over-reject/incidental', $got->typeHandling?->isIncidental(), true);
expect('over-reject/lines', $got->typeHandling?->overRejectedLines, [10]);

// Unmarked valid call (no // V) that type-rejects is still over-rejection.
$got = $evaluator->evaluate(
    [$e(20)],
    [
        10 => ['Parameter expects number, 1 given. [identifier=argument.type]'],
        20 => ['Parameter expects number, true given. [identifier=argument.type]'],
    ],
    'phpstan',
    [$t(5)],
);
expect('unmarked-fp/incidental', $got->typeHandling?->isIncidental(), true);
expect('unmarked-fp/over-rejected', $got->typeHandling?->overRejectedLines, [10]);
expect('unmarked-fp/false-positives', $got->typeHandling?->falsePositiveLines, [10]);

// Unrelated unused-parameter FP must not demote Enforced to incidental.
$got = $evaluator->evaluate(
    [$e(20)],
    [
        8 => ['Unused parameter $helper.'],
        20 => ['Parameter expects int, string given. [identifier=argument.type]'],
    ],
    'phpstan',
    [$t(5)],
);
expect('unused-fp/incidental', $got->typeHandling?->isIncidental(), false);
expect('unused-fp/enforcement', $got->typeHandling?->enforcement, TypeHandling::ENFORCED);
expect('unused-fp/false-positives', $got->typeHandling?->falsePositiveLines, [8]);

// Intelephense P1131 on the declaration is not a recognition failure.
$got = $evaluator->evaluate(
    [$e(20)],
    [5 => ['Documented type is not compatible with the declared type. [P1131]']],
    'intelephense',
    [$t(5, 'associative-array<int, string>')],
);
expect('p1131/recognition', $got->typeHandling?->recognition, TypeHandling::RECOGNIZED);
expect('p1131/unrecognized-lines', $got->typeHandling?->unrecognizedLines, []);
expect('p1131/verdict', $got->conformanceAutomated, 'Pass');

// A real undeclared-type diagnostic is unrecognized.
$got = $evaluator->evaluate(
    [$e(20)],
    [
        5 => ['Parameter $value has undeclared type \\Foo\\number [PhanUndeclaredTypeParameter]'],
        20 => ['Argument 1 is true but takes \\Foo\\number [PhanTypeMismatchArgumentProbablyReal]'],
    ],
    'phan',
    [$t(5)],
);
expect('undeclared/recognition', $got->typeHandling?->recognition, TypeHandling::UNRECOGNIZED);
expect('undeclared/incidental', $got->typeHandling?->isIncidental(), true);
expect('undeclared/enforcement', $got->typeHandling?->enforcement, TypeHandling::ENFORCED);

// dumpType undefined-function is not enforcement.
$got = $evaluator->evaluate(
    [$e(12)],
    [12 => ['Function PHPStan\\dumpType not found. [UndefinedFunction]']],
    'psalm',
    [$t(8, 'PHPStan\\dumpType')],
);
expect('dump-noise/enforcement', $got->typeHandling?->enforcement, TypeHandling::NONE);
expect('dump-noise/verdict', $got->conformanceAutomated, 'Pass');

// Optional E silence on a T-row is Pass + not enforced, not a silent "success".
$got = $evaluator->evaluate(
    [$e(20)],
    [],
    'phpy',
    [$t(5)],
);
expect('silent-T/verdict', $got->conformanceAutomated, 'Pass');
expect('silent-T/enforcement', $got->typeHandling?->enforcement, TypeHandling::NONE);
expect('silent-T/recognition', $got->typeHandling?->recognition, TypeHandling::RECOGNIZED);

// Required E silence is Fail.
$got = $evaluator->evaluate(
    [$e(20, required: true)],
    [],
    'phpstan',
    [],
);
expect('required-miss/verdict', $got->conformanceAutomated, 'Fail');

// Phan / Mago class-name fallback on an explicit // V line.
$got = $evaluator->evaluate(
    [$e(20), $e(10, valid: true)],
    [
        10 => ['Argument 1 ($value) is 1 of type int but takes \\Foo\\number (no real type) [PhanTypeMismatchArgumentProbablyReal]'],
        20 => ['Argument 1 ($value) is true but takes \\Foo\\number [PhanTypeMismatchArgumentProbablyReal]'],
    ],
    'phan',
    [$t(5)],
);
expect('phan-v/verdict', $got->conformanceAutomated, 'Fail');
expect('phan-v/over-rejected', $got->typeHandling?->overRejectedLines, [10]);
expect('phan-v/incidental', $got->typeHandling?->isIncidental(), true);

$got = $evaluator->evaluate(
    [$e(20), $e(10, valid: true)],
    [
        10 => ['Invalid argument type for argument #1: expected `unknown-ref(number)`, but found `int(1)`. [invalid-argument]'],
        20 => ['Invalid argument type for argument #1: expected `unknown-ref(number)`, but found `true`. [invalid-argument]'],
    ],
    'mago',
    [$t(5)],
);
expect('mago-v/over-rejected', $got->typeHandling?->overRejectedLines, [10]);

// Synonym style nit is not unrecognized.
$got = $evaluator->evaluate(
    [$e(20)],
    [10 => ['Use bool type instead of boolean [invalidDocblockType]']],
    'noverify',
    [$t(10, 'boolean')],
);
expect('synonym/recognition', $got->typeHandling?->recognition, TypeHandling::RECOGNIZED);

if ($failures > 0) {
    fwrite(STDERR, sprintf("\n%d failure(s)\n", $failures));
    exit(1);
}

echo "\nall passed\n";
