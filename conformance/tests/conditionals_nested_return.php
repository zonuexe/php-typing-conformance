<?php

declare(strict_types=1);

namespace Conformance\Tests\ConditionalsNestedReturn;

/**
 * Nested conditional return type.
 *
 * `int + int` is `int`; any float operand makes the result `float`.
 * Tools that expand both layers accept `add(1, 2)` as `int` and reject
 * `add(1, 1.5)` as `int`. Tools that fall back to the native `int|float`
 * reject the valid control as well.
 *
 * References:
 * - PHPStan 1.6.0: nested `($a is int ? ($b is int ? int : float) : float)` // E<noverify>: NoVerify reads $left in the condition as a class name
 * - PHPStan phpdoc-types: Conditional return types
 * - Psalm conditional_types.md: Nested conditionals
 * - Intelephense Type-System.md: Conditional Return Type
 */

/**
 * @param int|float $left
 * @param int|float $right
 * @return ($left is int ? ($right is int ? int : float) : float)
 */
function add(int|float $left, int|float $right): int|float // T: ($left is int ? ($right is int ? int : float) : float)
{
    if (is_int($left) && is_int($right)) {
        return $left + $right;
    }

    return 0.5;
}

function takesInt(int $value): void
{
}

function takesFloat(float $value): void
{
}

takesInt(add(1, 2)); // V: int + int is int
takesFloat(add(1, 1.5)); // V: int + float is float
takesFloat(add(1.5, 1)); // V: float + int is float

takesInt(add(1, 1.5)); // E?: int + float is float, not int
takesInt(add(1.5, 1)); // E?: float + int is float, not int
