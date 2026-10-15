<?php

declare(strict_types=1);

namespace Conformance\Tests\NativeTypesOverloadedNumberOperators;

use BcMath\Number;
use GMP;

/**
 * Arithmetic operators on `BcMath\Number` and `GMP`.
 *
 * PHP 8.4 overloads `+`, `-`, `*`, `/`, `%` and `**` on `BcMath\Number`.
 * `GMP` overloads the same set. A `Number * int` is a `Number` at runtime,
 * not `mixed` and not `int`. Tools that treat every non-scalar operand as
 * invalid still have to be asked whether the result is the class.
 *
 * Mago 1.57.0 models both classes but hides the operators behind
 * `allow-operator-overloading` (off by default): it types the result as
 * the class and still reports `invalid-operand`. This suite uses Mago's
 * defaults, so that report is part of the measurement.
 *
 * References:
 * - https://github.com/carthage-software/mago/issues/2486
 * - PHP 8.4 `BcMath\Number` operator overloading
 */

function takesNumber(Number $value): void
{
}

function takesGmp(GMP $value): void
{
}

function takesInt(int $value): void
{
}

function numberTimesInt(Number $price, int $quantity): void
{
    takesNumber($price * $quantity); // V: Number * int is Number
}

function numberPlusNumber(Number $left, Number $right): void
{
    takesNumber($left + $right); // V: Number + Number is Number
}

function gmpPlusGmp(GMP $left, GMP $right): void
{
    takesGmp($left + $right); // V: GMP + GMP is GMP
}

function gmpTimesInt(GMP $value, int $n): void
{
    takesGmp($value * $n); // V: GMP * int is GMP
}

function numberProductIsNotInt(Number $price): void
{
    takesInt($price * 2); // E: product is Number, not int
}

function gmpSumIsNotInt(GMP $value): void
{
    takesInt($value + $value); // E: sum is GMP, not int
}

function numberPlusGarbage(Number $price): void
{
    takesNumber($price + 'abc'); // E?: BCMath throws ValueError on a non-numeric string
}

function numberPlusNull(Number $price): void
{
    takesNumber($price + null); // E?: TypeError
}
