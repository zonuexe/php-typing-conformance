<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonNegativeFloat;

/**
 * `non-negative-float`
 *
 * Floats from zero up, `float<0, max>`. It differs from `positive-float` by
 * exactly one value, so zero is what the two spellings are for; `-0.1` is the
 * boundary on the other side. Analyzers that model the range reject a
 * negative literal; others fall back to plain `float` and accept it.
 *
 * References:
 * - https://phpstan.org/writing-php-code/phpdoc-types#float-ranges
 * - PHPStan 2.3.6 (phpstan/phpstan-src#6937)
 */

/**
 * @return non-negative-float
 */
function returnsNonNegativeFloat() // T: non-negative-float
{
    return 1.5; // V
}

function acceptsFloat(float $value): void
{
}

/**
 * @param non-negative-float $value
 */
function acceptsNonNegativeFloat($value): void // T: non-negative-float
{
}

// A `non-negative-float` value always satisfies a native `float` parameter.
acceptsFloat(returnsNonNegativeFloat()); // V

// Zero is inside the range — this is what separates the spelling from
// `positive-float` — and so is anything above it.
acceptsNonNegativeFloat(0.0); // V
acceptsNonNegativeFloat(1.5); // V

// One past the boundary.
acceptsNonNegativeFloat(-0.1); // E?: -0.1 is not a non-negative-float
