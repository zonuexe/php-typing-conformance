<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackDecimalIntString;

/**
 * `decimal-int-string`
 *
 * A string that spells an integer the way PHP writes one back, so it is cast to
 * `int` when used as an array key: `'0'`, `'1'`, `'1234'`, `'-1'`. A string like
 * `'007'` or `'+1'` is still an integer to `is_numeric()`, but it keeps its
 * string identity as a key, so the refinement excludes it. Analyzers that model
 * it reject a literal outside that set; others fall back to plain `string` and
 * accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `decimal-int-string` resolves to string&AccessoryDecimalIntegerStringType
 * - PHPStan AccessoryDecimalIntegerStringType: covers "0", "1", "1234", "-1"
 */

/**
 * @return decimal-int-string
 */
function returnsDecimalIntString() // T: decimal-int-string
{
    return '123'; // V
}

function acceptsString(string $value): void
{
}

/**
 * @param decimal-int-string $value
 */
function acceptsDecimalIntString($value): void // T: decimal-int-string
{
}

// A `decimal-int-string` value always satisfies a native `string` parameter.
acceptsString(returnsDecimalIntString()); // V

// Canonical decimal notation, including a negative one, satisfies the parameter.
acceptsDecimalIntString('123'); // V
acceptsDecimalIntString('-1'); // V

// Numeric, but not how PHP writes the integer back: enforcing analyzers reject
// these, others fall back to `string` and accept them.
acceptsDecimalIntString('007'); // E?: leading zeros survive as an array key, so '007' is not a decimal-int-string
acceptsDecimalIntString('+1'); // E?: '+1' survives as an array key, so it is not a decimal-int-string

// Not an integer at all.
acceptsDecimalIntString('abc'); // E?: 'abc' is not a decimal-int-string
