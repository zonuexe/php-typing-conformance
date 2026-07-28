<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonEmptyUppercaseString;

/**
 * `non-empty-uppercase-string`
 *
 * The intersection of two refinements: at least one character, and unchanged by
 * `strtoupper()`. The halves fail independently, so `''` is rejected only by the
 * length half and `'abc'` only by the case half — an analyzer can model one and
 * miss the other. Analyzers that model neither fall back to plain `string` and
 * accept both.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-empty-uppercase-string` resolves to string&AccessoryNonEmptyStringType&AccessoryUppercaseStringType
 */

/**
 * @return non-empty-uppercase-string
 */
function returnsNonEmptyUppercaseString() // T: non-empty-uppercase-string
{
    return 'ABC';
}

function acceptsString(string $value): void
{
}

/**
 * @param non-empty-uppercase-string $value
 */
function acceptsNonEmptyUppercaseString($value): void // T: non-empty-uppercase-string
{
}

// A `non-empty-uppercase-string` value always satisfies a native `string` parameter.
acceptsString(returnsNonEmptyUppercaseString());

// A non-empty string with nothing lowercase in it satisfies both halves.
acceptsNonEmptyUppercaseString('ABC');
acceptsNonEmptyUppercaseString('123');

// Uppercase, but empty.
acceptsNonEmptyUppercaseString(''); // E?: '' is not a non-empty-uppercase-string

// Non-empty, but not uppercase.
acceptsNonEmptyUppercaseString('abc'); // E?: 'abc' is not a non-empty-uppercase-string
