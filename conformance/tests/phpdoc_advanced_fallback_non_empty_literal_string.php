<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonEmptyLiteralString;

/**
 * `non-empty-literal-string`
 *
 * The intersection of two refinements: the string must come from source code
 * *and* must not be empty. Each half fails independently, so `''` is rejected
 * for being empty and a runtime string for not being literal — an analyzer can
 * model one half and miss the other. Analyzers that model neither fall back to
 * plain `string` and accept both.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-empty-literal-string` resolves to string&AccessoryNonEmptyStringType&AccessoryLiteralStringType
 */

/**
 * @return non-empty-literal-string
 */
function returnsNonEmptyLiteralString() // T: non-empty-literal-string
{
    return 'literal';
}

function acceptsString(string $value): void
{
}

/**
 * @param non-empty-literal-string $value
 */
function acceptsNonEmptyLiteralString($value): void // T: non-empty-literal-string
{
}

// A `non-empty-literal-string` value always satisfies a native `string` parameter.
acceptsString(returnsNonEmptyLiteralString());

// A non-empty source-code literal satisfies both halves.
acceptsNonEmptyLiteralString('x');

// Literal, but empty.
acceptsNonEmptyLiteralString(''); // E?: '' is not a non-empty-literal-string

function forwardsRuntimeString(string $value): void
{
    // Possibly non-empty, but not literal.
    acceptsNonEmptyLiteralString($value); // E?: an arbitrary runtime string is not a non-empty-literal-string
}
