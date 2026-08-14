<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackUppercaseString;

/**
 * `uppercase-string`
 *
 * A string unchanged by `strtoupper()`, the mirror of `lowercase-string`. A
 * string with no cased characters at all, `'123'` or `''`, satisfies both at
 * once; a single lowercase character disqualifies the whole string. Analyzers
 * that model the refinement reject such a literal; others fall back to plain
 * `string` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `uppercase-string` resolves to string&AccessoryUppercaseStringType
 */

/**
 * @return uppercase-string
 */
function returnsUppercaseString() // T: uppercase-string
{
    return 'ABC'; // V
}

function acceptsString(string $value): void
{
}

/**
 * @param uppercase-string $value
 */
function acceptsUppercaseString($value): void // T: uppercase-string
{
}

// An `uppercase-string` value always satisfies a native `string` parameter.
acceptsString(returnsUppercaseString()); // V

// Uppercase letters satisfy the parameter, and so does a string with nothing
// to uppercase.
acceptsUppercaseString('ABC'); // V
acceptsUppercaseString('123'); // V
acceptsUppercaseString(''); // V

// A fully lowercase literal is outside the refinement.
acceptsUppercaseString('abc'); // E?: 'abc' is not an uppercase-string

// So is a mixed one, on the strength of a single character.
acceptsUppercaseString('ABc'); // E?: 'ABc' is not an uppercase-string
