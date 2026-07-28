<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackLowercaseString;

/**
 * `lowercase-string`
 *
 * A string unchanged by `strtolower()`. That is a weaker claim than "made of
 * lowercase letters": a string with no cased characters at all, `'123'` or `''`,
 * qualifies. A single uppercase character anywhere disqualifies the whole
 * string. Analyzers that model the refinement reject such a literal; others
 * fall back to plain `string` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `lowercase-string` resolves to string&AccessoryLowercaseStringType
 */

/**
 * @return lowercase-string
 */
function returnsLowercaseString() // T: lowercase-string
{
    return 'abc';
}

function acceptsString(string $value): void
{
}

/**
 * @param lowercase-string $value
 */
function acceptsLowercaseString($value): void // T: lowercase-string
{
}

// A `lowercase-string` value always satisfies a native `string` parameter.
acceptsString(returnsLowercaseString());

// Lowercase letters satisfy the parameter, and so does a string with nothing
// to lowercase.
acceptsLowercaseString('abc');
acceptsLowercaseString('123');
acceptsLowercaseString('');

// A fully uppercase literal is outside the refinement.
acceptsLowercaseString('ABC'); // E?: 'ABC' is not a lowercase-string

// So is a mixed one, on the strength of a single character.
acceptsLowercaseString('abC'); // E?: 'abC' is not a lowercase-string
