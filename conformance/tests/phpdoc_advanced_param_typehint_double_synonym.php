<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintDoubleSynonym;

/**
 * `double` PHPDoc synonym is compatible with native `float`.
 *
 * `double` (PHPDoc) and native `float` name the same type, so a value that one
 * side documents as `@return double` should satisfy a native `float`
 * parameter, and vice versa, while `double` is still enforced as a real float.
 *
 * References:
 * - PHPStan TypeNodeResolver `double` case resolves to FloatType
 */

/**
 * @return double
 */
function returnsDouble() // T: double
{
    return 1.5;
}

function acceptsOnlyFloat(float $value): void
{
}

/**
 * @param double $value
 */
function acceptsOnlyDouble($value): void // T: double
{
}

// A `double`-documented value should satisfy a native `float` parameter.
acceptsOnlyFloat(returnsDouble());

// A native `float` should satisfy a `double`-documented parameter.
acceptsOnlyDouble(1.5);

// `double` must still be enforced as a real float.
acceptsOnlyDouble('not a float'); // E?: string should be rejected where @param double is required
