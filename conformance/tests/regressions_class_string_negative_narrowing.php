<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsClassStringNegativeNarrowing;

/**
 * Negative-branch narrowing via a class-string identity comparison.
 *
 * After `if ($x::class === A::class) { throw; }`, the fall-through has excluded A (which is
 * final), so `$x` is `B` and `return $x` satisfies the `: B` return type. Analyzers diverge
 * on whether they narrow the *negative* branch of a `::class` identity comparison.
 *
 * PHPStan narrows the positive branch of such comparisons but not the negative one — even
 * though it narrows both directions for `instanceof`, `is_a()`, and `gettype()` — so it
 * keeps `A|B` on the fall-through and reports a false-positive return.type.
 *
 * Source lead: Mago analyzer test `narrow_non_final_class_string_match`.
 */

final class A {}
final class B {}

function fromClassString(A|B $x): B // E<psalm>: declares the return invalid because $x is not narrowed to B (InvalidReturnType)
{
    if ($x::class === A::class) {
        throw new \LogicException('not A');
    }

    return $x; // E<phpstan>: negative branch of ::class === keeps A|B (final A not subtracted), so return.type fires // E<phpstan-strict>: same // E<psalm>: InvalidReturnStatement, $x stays A|B. Mago and Phan narrow to B and stay clean
}
