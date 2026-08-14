<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonPositiveInt;

/**
 * `non-positive-int`
 *
 * The integers up to and including zero, `int<min, 0>`. It differs from
 * `negative-int` by exactly one value, so zero is what the two spellings are
 * for; `1` is the boundary on the other side. Analyzers that model the range
 * reject a positive literal; others fall back to plain `int` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-positive-int` resolves to IntegerRangeType::fromInterval(null, 0)
 */

/**
 * @return non-positive-int
 */
function returnsNonPositiveInt() // T: non-positive-int
{
    return -5; // V
}

function acceptsInt(int $value): void
{
}

/**
 * @param non-positive-int $value
 */
function acceptsNonPositiveInt($value): void // T: non-positive-int
{
}

// A `non-positive-int` value always satisfies a native `int` parameter.
acceptsInt(returnsNonPositiveInt()); // V

// Zero is inside the range — this is what separates the spelling from
// `negative-int` — and so is anything below it.
acceptsNonPositiveInt(0); // V
acceptsNonPositiveInt(-1); // V

// One past the boundary.
acceptsNonPositiveInt(1); // E?: 1 is not a non-positive-int
