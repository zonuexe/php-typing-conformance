<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNegativeInt;

/**
 * `negative-int`
 *
 * The integers below zero, `int<min, -1>`. Zero is the interesting probe: it is
 * neither positive nor negative, and an analyzer that reads the name as "not
 * positive" lets it through. Analyzers that model the range reject it; others
 * fall back to plain `int` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `negative-int` resolves to IntegerRangeType::fromInterval(null, -1)
 */

/**
 * @return negative-int
 */
function returnsNegativeInt() // T: negative-int
{
    return -5;
}

function acceptsInt(int $value): void
{
}

/**
 * @param negative-int $value
 */
function acceptsNegativeInt($value): void // T: negative-int
{
}

// A `negative-int` value always satisfies a native `int` parameter.
acceptsInt(returnsNegativeInt());

// The upper bound of the range satisfies the parameter.
acceptsNegativeInt(-1);

// Zero sits just outside it.
acceptsNegativeInt(0); // E?: 0 is not a negative-int

// And a positive literal is plainly outside it.
acceptsNegativeInt(5); // E?: 5 is not a negative-int
