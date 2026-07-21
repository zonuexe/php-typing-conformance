<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackPositiveInt;

/**
 * `positive-int` falls back to `int` for cross-boundary compatibility.
 *
 * `positive-int` is a refinement of `int`, so a `@return positive-int` value
 * always satisfies a native `int` parameter — analyzers that do not model the
 * range still fall back to `int`. Range-aware analyzers additionally reject a
 * value known to be out of range; others accept it under the `int` fallback.
 *
 * References:
 * - PHPStan TypeNodeResolver `positive-int` resolves to IntegerRangeType(1, null)
 */

/**
 * @return positive-int
 */
function returnsPositiveInt()
{
    return 5;
}

function acceptsInt(int $value): void
{
}

/**
 * @param positive-int $value
 */
function acceptsPositiveInt($value): void
{
}

// A `positive-int` value always satisfies a native `int` parameter.
acceptsInt(returnsPositiveInt());

// A literal positive integer satisfies the refined parameter.
acceptsPositiveInt(5);

// A negative literal is out of range; range-aware analyzers reject it, others
// fall back to `int` and accept it.
acceptsPositiveInt(-1); // E?: negative int should be rejected where positive-int is required
