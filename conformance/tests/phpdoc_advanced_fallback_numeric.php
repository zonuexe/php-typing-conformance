<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNumeric;

/**
 * `numeric`
 *
 * `int|float|numeric-string` — `number` widened by exactly one member. It is
 * the PHPDoc spelling of what `is_numeric()` accepts, so `'123'` and `'1.5e3'`
 * are in and `'abc'` is out. Analyzers that model it reject a non-numeric
 * literal; others fall back to `mixed` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `numeric` resolves to int|float|(string&AccessoryNumericStringType)
 */

/**
 * @return numeric
 */
function returnsNumeric() // T: numeric
{
    $values = [1, 1.5, '123'];

    return $values[\array_rand($values)];
}

/**
 * @param numeric $value
 */
function acceptsNumeric($value): void // T: numeric
{
}

acceptsNumeric(returnsNumeric());

// Numbers and numeric strings alike satisfy the parameter.
acceptsNumeric(1);
acceptsNumeric(1.5);
acceptsNumeric('123');
acceptsNumeric('1.5e3');

// A string that is not numeric does not.
acceptsNumeric('abc'); // E?: 'abc' is not numeric

// Nor does a bool.
acceptsNumeric(true); // E?: true is not numeric
