<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyScalar;

/**
 * `scalar` / `number` / `numeric` broad types.
 *
 * `scalar` is `int|float|string|bool`; `number` is `int|float`; `numeric` is
 * `int|float|numeric-string`. Analyzers that model them reject a value outside
 * the union; others may fall back more loosely.
 *
 * References:
 * - PHPStan TypeNodeResolver scalar / number / numeric / non-empty-scalar
 */

/** @param scalar $value */
function acceptsScalar($value): void // T: scalar
{
}

/** @param number $value */
function acceptsNumber($value): void // T: number
{
}

/** @param numeric $value */
function acceptsNumeric($value): void // T: numeric
{
}

acceptsScalar(1);
acceptsScalar('x');
acceptsScalar([1, 2]); // E?: an array is not a scalar

acceptsNumber(1);
acceptsNumber(1.5);
acceptsNumber('1'); // E?: a string is not a number (int|float)

acceptsNumeric(1);
acceptsNumeric('123');
acceptsNumeric('abc'); // E?: 'abc' is not numeric
