<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNumber;

/**
 * `number`
 *
 * `int|float`, and nothing else — a numeric string is not a `number`. That is
 * the whole distinction from `numeric`, which does include one. Analyzers that
 * model the union reject the string; others fall back to `mixed` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `number` resolves to int|float, after trying a pseudo-type class
 */

/**
 * @return number
 */
function returnsNumber() // T: number
{
    return \random_int(0, 1) === 1 ? 1 : 1.5;
}

/**
 * @param number $value
 */
function acceptsNumber($value): void // T: number
{
}

acceptsNumber(returnsNumber());

// Both members satisfy the parameter.
acceptsNumber(1);
acceptsNumber(1.5);

// A numeric string does not: `number` is narrower than `numeric`.
acceptsNumber('1'); // E?: a numeric string is not a number (int|float)

// Nor does a bool, however freely PHP would juggle it.
acceptsNumber(true); // E?: true is not a number (int|float)
