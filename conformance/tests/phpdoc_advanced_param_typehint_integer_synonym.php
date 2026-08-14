<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintIntegerSynonym;

/**
 * `integer` PHPDoc synonym is compatible with native `int`.
 *
 * `integer` (PHPDoc) and native `int` name the same type, so a value that one
 * side documents as `@return integer` should satisfy a native `int` parameter,
 * and vice versa, while `integer` is still enforced as a real integer type.
 *
 * References:
 * - PHPStan TypeNodeResolver `integer` case resolves to IntegerType
 */

/**
 * @return integer
 */
function returnsInteger() // T: integer
{
    return 1; // V
}

function acceptsOnlyInt(int $value): void
{
}

/**
 * @param integer $value
 */
function acceptsOnlyInteger($value): void // T: integer
{
}

// An `integer`-documented value should satisfy a native `int` parameter.
acceptsOnlyInt(returnsInteger()); // V

// A native `int` should satisfy an `integer`-documented parameter.
acceptsOnlyInteger(1); // V

// `integer` must still be enforced as a real integer.
acceptsOnlyInteger('not an int'); // E?: string should be rejected where @param integer is required
