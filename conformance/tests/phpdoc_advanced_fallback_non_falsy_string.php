<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonFalsyString;

/**
 * `non-falsy-string`
 *
 * The same type as `truthy-string` — PHPStan resolves both spellings through
 * one `case` — so this file asks a question about vocabulary rather than about
 * semantics: which analyzers know this second name for it. The probes are the
 * two falsy literals, `''` and `'0'`. Analyzers that do not know the spelling
 * fall back to plain `string` and accept both.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-falsy-string` resolves to string&AccessoryNonFalsyStringType
 * - PHPStan TypeNodeResolver: `truthy-string` and `non-falsy-string` are the same case
 */

/**
 * @return non-falsy-string
 */
function returnsNonFalsyString() // T: non-falsy-string
{
    return 'x'; // V
}

function acceptsString(string $value): void
{
}

/**
 * @param non-falsy-string $value
 */
function acceptsNonFalsyString($value): void // T: non-falsy-string
{
}

// A `non-falsy-string` value always satisfies a native `string` parameter.
acceptsString(returnsNonFalsyString()); // V

// A string that passes a boolean test satisfies the parameter.
acceptsNonFalsyString('x'); // V

// The empty string is falsy.
acceptsNonFalsyString(''); // E?: '' is not a non-falsy-string

// So is '0', which is where `non-falsy-string` parts company with
// `non-empty-string`.
acceptsNonFalsyString('0'); // E?: '0' is non-empty but falsy, so it is not a non-falsy-string
