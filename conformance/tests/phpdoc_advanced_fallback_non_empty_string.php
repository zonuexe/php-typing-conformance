<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonEmptyString;

/**
 * `non-empty-string`
 *
 * A string of length at least one. It is a length constraint, not a truthiness
 * one, so `'0'` — falsy in PHP — is a perfectly good `non-empty-string`; that is
 * what separates it from `truthy-string`. Analyzers that model the refinement
 * reject `''`; others fall back to plain `string` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-empty-string` resolves to string&AccessoryNonEmptyStringType
 */

/**
 * @return non-empty-string
 */
function returnsNonEmptyString() // T: non-empty-string
{
    return 'x';
}

function acceptsString(string $value): void
{
}

/**
 * @param non-empty-string $value
 */
function acceptsNonEmptyString($value): void // T: non-empty-string
{
}

// A `non-empty-string` value always satisfies a native `string` parameter.
acceptsString(returnsNonEmptyString());

// Any string with at least one character satisfies the parameter — `'0'`
// included, however falsy it is.
acceptsNonEmptyString('x');
acceptsNonEmptyString('0');

// The empty string: enforcing analyzers reject it, others fall back to `string`
// and accept it.
acceptsNonEmptyString(''); // E?: '' is not a non-empty-string
