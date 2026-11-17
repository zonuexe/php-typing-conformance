<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackFiniteFloat;

/**
 * `finite-float`
 *
 * Any float except `INF`, `-INF`, and `NAN`. Zero and ordinary magnitudes
 * are inside the range; the three IEEE specials are the probes. Analyzers
 * that model the spelling reject those three; others fall back to plain
 * `float` and accept them.
 *
 * References:
 * - https://phpstan.org/writing-php-code/phpdoc-types#float-ranges
 * - PHPStan 2.3.6 (phpstan/phpstan-src#6937)
 */

/**
 * @return finite-float
 */
function returnsFiniteFloat() // T: finite-float
{
    return 1.5; // V
}

function acceptsFloat(float $value): void
{
}

/**
 * @param finite-float $value
 */
function acceptsFiniteFloat($value): void // T: finite-float
{
}

// A `finite-float` value always satisfies a native `float` parameter.
acceptsFloat(returnsFiniteFloat()); // V

// Ordinary magnitudes, including zero, are finite.
acceptsFiniteFloat(1.5); // V
acceptsFiniteFloat(0.0); // V
acceptsFiniteFloat(-1.5); // V

// The three values the spelling exists to exclude.
acceptsFiniteFloat(INF); // E?: INF is not a finite-float
acceptsFiniteFloat(-INF); // E?: -INF is not a finite-float
acceptsFiniteFloat(NAN); // E?: NAN is not a finite-float
