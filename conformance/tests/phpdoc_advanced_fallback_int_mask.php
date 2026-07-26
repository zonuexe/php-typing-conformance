<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackIntMask;

/**
 * `int-mask<1, 2, 4>`
 *
 * `int-mask<1, 2, 4>` describes every bitwise-or combination of the flags
 * (0..7), a refinement of `int`, so a `@return int-mask<...>` value always
 * satisfies a native `int` parameter. Analyzers that model the mask reject a
 * value that is not a valid combination; others fall back to `int`.
 *
 * References:
 * - PHPStan TypeNodeResolver `int-mask` expands to the union of flag combinations
 */

/**
 * @return int-mask<1, 2, 4>
 */
function returnsFlags() // T: int-mask<1, 2, 4>
{
    return 1 | 4;
}

function acceptsInt(int $value): void
{
}

/**
 * @param int-mask<1, 2, 4> $flags
 */
function acceptsFlags($flags): void // T: int-mask<1, 2, 4>
{
}

// An `int-mask` value always satisfies a native `int` parameter.
acceptsInt(returnsFlags());

// A valid combination (1 | 4 = 5) satisfies the refined parameter.
acceptsFlags(1 | 4);

// A value outside the mask combinations: mask-aware analyzers reject it, others
// fall back to `int` and accept it.
acceptsFlags(8); // E?: 8 is not a combination of the int-mask<1, 2, 4> flags
