<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsArrayElementNullSubtraction;

/**
 * Element-type narrowing by subtracting the null case.
 *
 * `$arr = [$val]` has type `list{string|null}`. After `if ($arr === [null])`, the else
 * branch has excluded the only value where the element is null, so `$arr` narrows to
 * `list{string}` and a call requiring `list{string}` should type-check. Analyzers diverge
 * on whether they perform this equality-based element subtraction on the array shape.
 *
 * Source lead: Mago analyzer test `array_reconcile` (crates/analyzer/tests/cases),
 * function `test_null_string_subtraction`. Mago narrows and accepts the guarded call;
 * this file records every tool's verdict on the same construct.
 */

/** @param list{string} $arr */
function takesStringList(array $arr): void // E<noverify>: NoVerify cannot evaluate the list{string} shape param and flags the signature itself
{
    printf("Count: %d\n", count($arr));
}

function caller(string|null $val): void
{
    $arr = [$val];

    if ($arr === [null]) {
        // the only null-element value is excluded here
        return;
    }

    takesStringList($arr); // E<phpstan>: does not subtract the null case via === [null], so $arr stays array{string|null} and reports argument.type // E<phpstan-strict>: same coercion under strict rules // E<psalm>: reports ArgumentTypeCoercion; element type stays string|null. Mago and Phan narrow to list{string} and stay clean
}
