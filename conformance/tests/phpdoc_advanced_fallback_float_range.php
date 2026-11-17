<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackFloatRange;

/**
 * `float<0, 1.5>`
 *
 * Bounded refinement of `float`. Bounds are inclusive, and may be integer or
 * float literals (`float<min, 100.0>`, `float<0.5, max>`). A
 * `@return float<0, 1.5>` value always satisfies a native `float` parameter
 * — analyzers that do not model the range still fall back to `float`.
 * Range-aware analyzers reject a literal outside the bounds, and treat
 * `$n > 1.5` on a value already in the range as always false.
 *
 * References:
 * - https://phpstan.org/writing-php-code/phpdoc-types#float-ranges
 * - PHPStan 2.3.6 (phpstan/phpstan-src#6937)
 */

/**
 * @return float<0, 1.5>
 */
function returnsUnitInterval() // T: float<0, 1.5>
{
    return 0.75; // V
}

function acceptsFloat(float $value): void
{
}

/**
 * @param float<0, 1.5> $value
 */
function acceptsUnitInterval($value): void // T: float<0, 1.5>
{
}

// A `float<0, 1.5>` value always satisfies a native `float` parameter.
acceptsFloat(returnsUnitInterval()); // V

// Both inclusive bounds, and a point between them, satisfy the parameter.
acceptsUnitInterval(0.0); // V
acceptsUnitInterval(1.5); // V
acceptsUnitInterval(0.75); // V

// Just outside either bound.
acceptsUnitInterval(-0.1); // E?: -0.1 is below float<0, 1.5>
acceptsUnitInterval(1.6); // E?: 1.6 is above float<0, 1.5>

/**
 * @param float<0, 1.5> $n
 */
function alreadyBounded(float $n): void // T: float<0, 1.5>
{
    if ($n > 1.5) { // E?: the inclusive upper bound makes > 1.5 impossible
        echo 'unreachable';
    }

    if ($n >= 1.5) { // Q: 1.5 is still in the range
        echo 'maybe the bound';
    }
}
