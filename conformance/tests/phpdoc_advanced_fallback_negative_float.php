<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNegativeFloat;

/**
 * `negative-float`
 *
 * Floats strictly less than zero. Zero is the interesting probe: it is
 * neither positive nor negative. `-INF` is less than zero, so it satisfies
 * this spelling. Analyzers that model the range reject `0.0`; others fall
 * back to plain `float` and accept it.
 *
 * References:
 * - https://phpstan.org/writing-php-code/phpdoc-types#float-ranges
 * - PHPStan 2.3.6 (phpstan/phpstan-src#6937)
 */

/**
 * @return negative-float
 */
function returnsNegativeFloat() // T: negative-float
{
    return -1.5; // V
}

function acceptsFloat(float $value): void
{
}

/**
 * @param negative-float $value
 */
function acceptsNegativeFloat($value): void // T: negative-float
{
}

// A `negative-float` value always satisfies a native `float` parameter.
acceptsFloat(returnsNegativeFloat()); // V

// A literal less than zero satisfies the refined parameter.
acceptsNegativeFloat(-1.5); // V

// -INF is less than zero.
acceptsNegativeFloat(-INF); // V

// Zero sits just outside it.
acceptsNegativeFloat(0.0); // E?: 0.0 is not a negative-float

// A positive literal is plainly outside it.
acceptsNegativeFloat(1.5); // E?: 1.5 is not a negative-float
