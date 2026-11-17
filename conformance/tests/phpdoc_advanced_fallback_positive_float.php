<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackPositiveFloat;

/**
 * `positive-float`
 *
 * Floats strictly greater than zero. Zero is the interesting probe: it is
 * finite and non-negative, but not positive. `+INF` is greater than zero, so
 * it satisfies this spelling; `finite-float` is the one that excludes
 * infinities. Analyzers that model the range reject `0.0`; others fall back
 * to plain `float` and accept it.
 *
 * References:
 * - https://phpstan.org/writing-php-code/phpdoc-types#float-ranges
 * - PHPStan 2.3.6 (phpstan/phpstan-src#6937)
 */

/**
 * @return positive-float
 */
function returnsPositiveFloat() // T: positive-float
{
    return 1.5; // V
}

function acceptsFloat(float $value): void
{
}

/**
 * @param positive-float $value
 */
function acceptsPositiveFloat($value): void // T: positive-float
{
}

// A `positive-float` value always satisfies a native `float` parameter.
acceptsFloat(returnsPositiveFloat()); // V

// A literal greater than zero satisfies the refined parameter.
acceptsPositiveFloat(1.5); // V

// +INF is greater than zero.
acceptsPositiveFloat(INF); // V

// Zero sits just outside the range — this is what separates the spelling from
// `non-negative-float`.
acceptsPositiveFloat(0.0); // E?: 0.0 is not a positive-float

// A negative literal is plainly outside it.
acceptsPositiveFloat(-1.0); // E?: -1.0 is not a positive-float
