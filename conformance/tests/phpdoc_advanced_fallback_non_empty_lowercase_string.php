<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonEmptyLowercaseString;

/**
 * `non-empty-lowercase-string`
 *
 * The intersection of two refinements: at least one character, and unchanged by
 * `strtolower()`. The halves fail independently, so `''` is rejected only by the
 * length half and `'ABC'` only by the case half — an analyzer can model one and
 * miss the other. Analyzers that model neither fall back to plain `string` and
 * accept both.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-empty-lowercase-string` resolves to string&AccessoryNonEmptyStringType&AccessoryLowercaseStringType
 */

/**
 * @return non-empty-lowercase-string
 */
function returnsNonEmptyLowercaseString() // T: non-empty-lowercase-string
{
    return 'abc'; // V
}

function acceptsString(string $value): void
{
}

/**
 * @param non-empty-lowercase-string $value
 */
function acceptsNonEmptyLowercaseString($value): void // T: non-empty-lowercase-string
{
}

// A `non-empty-lowercase-string` value always satisfies a native `string` parameter.
acceptsString(returnsNonEmptyLowercaseString()); // V

// A non-empty string with nothing uppercase in it satisfies both halves.
acceptsNonEmptyLowercaseString('abc'); // V
acceptsNonEmptyLowercaseString('123'); // V

// Lowercase, but empty.
acceptsNonEmptyLowercaseString(''); // E?: '' is not a non-empty-lowercase-string

// Non-empty, but not lowercase.
acceptsNonEmptyLowercaseString('ABC'); // E?: 'ABC' is not a non-empty-lowercase-string
