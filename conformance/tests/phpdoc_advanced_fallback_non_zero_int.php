<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonZeroInt;

/**
 * `non-zero-int`
 *
 * The only sign refinement that is not one interval: PHPStan resolves it to
 * `int<min, -1>|int<1, max>`, a union with a hole punched at zero. An analyzer
 * that flattens the union back into a single range loses the hole and accepts
 * `0`, which is the one value the spelling exists to exclude. Analyzers that
 * model neither fall back to plain `int` and accept it too.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-zero-int` resolves to a union of IntegerRangeType(min, -1) and IntegerRangeType(1, max)
 */

/**
 * @return non-zero-int
 */
function returnsNonZeroInt() // T: non-zero-int
{
    return \random_int(0, 1) === 1 ? 1 : -1;
}

function acceptsInt(int $value): void
{
}

/**
 * @param non-zero-int $value
 */
function acceptsNonZeroInt($value): void // T: non-zero-int
{
}

// A `non-zero-int` value always satisfies a native `int` parameter.
acceptsInt(returnsNonZeroInt());

// Both sides of the hole satisfy the parameter.
acceptsNonZeroInt(1);
acceptsNonZeroInt(-1);

// The hole itself.
acceptsNonZeroInt(0); // E?: 0 is not a non-zero-int
