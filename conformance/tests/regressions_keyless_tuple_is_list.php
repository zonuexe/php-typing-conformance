<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsKeylessTupleIsList;

/**
 * `array_is_list()` on a keyless tuple shape.
 *
 * `array{int, string}` has implicit sequential integer keys 0 and 1, so it is always a
 * list and `array_is_list($t)` is always true — the counterpart of the always-false
 * optional-key cases. This records whether each tool treats the keyless tuple as a
 * guaranteed list (reporting the check as redundantly true, or narrowing silently) and
 * confirms consistency with the explicit-key list-ness case.
 *
 * Reference: https://github.com/phpstan/phpstan/discussions/14939
 */

/** @param array{int, string} $t */
function keylessTupleIsList(array $t): void // E<noverify>: NoVerify does not parse array shape syntax and flags the annotation itself
{
    if (array_is_list($t)) { // E<phpstan>: correctly always-true — keyless tuple is a list (function.alreadyNarrowedType) // E<phpstan-strict>: same always-true verdict // E<psalm>: RedundantCondition — always a list // E<mago>: redundant-condition — always true. Phan alone emits no redundancy note. Contrast with the always-false optional-key bug
        echo "always reached — a keyless tuple is a list\n";
    }
}

keylessTupleIsList([1, 'x']);
