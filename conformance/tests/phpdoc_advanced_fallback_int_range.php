<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackIntRange;

/**
 * `int<0, 255>`
 *
 * `int<min, max>` is a bounded refinement of `int`, so a `@return int<0, 255>`
 * value always satisfies a native `int` parameter — analyzers that do not model
 * the range still fall back to `int`. Range-aware analyzers additionally reject
 * a literal outside the bounds.
 *
 * References:
 * - PHPStan TypeNodeResolver `int<min, max>` resolves to IntegerRangeType
 */

/**
 * @return int<0, 255>
 */
function returnsByte() // T: int<0, 255>
{
    return 200;
}

function acceptsInt(int $value): void
{
}

/**
 * @param int<0, 255> $value
 */
function acceptsByte($value): void // T: int<0, 255>
{
}

// An `int<0, 255>` value always satisfies a native `int` parameter.
acceptsInt(returnsByte());

// An in-range literal satisfies the refined parameter.
acceptsByte(200);

// An out-of-range literal: range-aware analyzers reject it, others fall back to
// `int` and accept it.
acceptsByte(256); // E?: 256 is above the int<0, 255> range
