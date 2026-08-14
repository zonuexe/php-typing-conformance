<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonNegativeInt;

/**
 * `non-negative-int`
 *
 * The integers from zero up, `int<0, max>`. It differs from `positive-int` by
 * exactly one value, so zero is what the two spellings are for; `-1` is the
 * boundary on the other side. Analyzers that model the range reject a negative
 * literal; others fall back to plain `int` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-negative-int` resolves to IntegerRangeType::fromInterval(0, null)
 */

/**
 * @return non-negative-int
 */
function returnsNonNegativeInt() // T: non-negative-int
{
    return 5; // V
}

function acceptsInt(int $value): void
{
}

/**
 * @param non-negative-int $value
 */
function acceptsNonNegativeInt($value): void // T: non-negative-int
{
}

// A `non-negative-int` value always satisfies a native `int` parameter.
acceptsInt(returnsNonNegativeInt()); // V

// Zero is inside the range — this is what separates the spelling from
// `positive-int` — and so is anything above it.
acceptsNonNegativeInt(0); // V
acceptsNonNegativeInt(1); // V

// One past the boundary.
acceptsNonNegativeInt(-1); // E?: -1 is not a non-negative-int
