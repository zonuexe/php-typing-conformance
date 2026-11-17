<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonPositiveFloat;

/**
 * `non-positive-float`
 *
 * Floats up to and including zero, `float<min, 0>`. It differs from
 * `negative-float` by exactly one value, so zero is what the two spellings
 * are for; `0.1` is the boundary on the other side. Analyzers that model the
 * range reject a positive literal; others fall back to plain `float` and
 * accept it.
 *
 * References:
 * - https://phpstan.org/writing-php-code/phpdoc-types#float-ranges
 * - PHPStan 2.3.6 (phpstan/phpstan-src#6937)
 */

/**
 * @return non-positive-float
 */
function returnsNonPositiveFloat() // T: non-positive-float
{
    return -1.5; // V
}

function acceptsFloat(float $value): void
{
}

/**
 * @param non-positive-float $value
 */
function acceptsNonPositiveFloat($value): void // T: non-positive-float
{
}

// A `non-positive-float` value always satisfies a native `float` parameter.
acceptsFloat(returnsNonPositiveFloat()); // V

// Zero is inside the range — this is what separates the spelling from
// `negative-float` — and so is anything below it.
acceptsNonPositiveFloat(0.0); // V
acceptsNonPositiveFloat(-1.5); // V

// One past the boundary.
acceptsNonPositiveFloat(0.1); // E?: 0.1 is not a non-positive-float
