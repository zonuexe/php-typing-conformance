<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackTruthyString;

/**
 * `truthy-string`
 *
 * A string that survives a boolean test, which in PHP excludes two literals
 * rather than one: `''` and `'0'`. The `'0'` case is the whole point of the
 * spelling — an analyzer that quietly treats it as `non-empty-string` accepts a
 * value that fails `if ($s)`. Analyzers that model neither refinement fall back
 * to plain `string` and accept both.
 *
 * References:
 * - PHPStan TypeNodeResolver `truthy-string` resolves to string&AccessoryNonFalsyStringType
 * - PHPStan TypeNodeResolver: `truthy-string` and `non-falsy-string` are the same case
 */

/**
 * @return truthy-string
 */
function returnsTruthyString() // T: truthy-string
{
    return 'x'; // V
}

function acceptsString(string $value): void
{
}

/**
 * @param truthy-string $value
 */
function acceptsTruthyString($value): void // T: truthy-string
{
}

// A `truthy-string` value always satisfies a native `string` parameter.
acceptsString(returnsTruthyString()); // V

// A string that passes a boolean test satisfies the parameter.
acceptsTruthyString('x'); // V

// The empty string is falsy.
acceptsTruthyString(''); // E?: '' is not a truthy-string

// So is '0', which is where `truthy-string` parts company with
// `non-empty-string`.
acceptsTruthyString('0'); // E?: '0' is non-empty but falsy, so it is not a truthy-string
