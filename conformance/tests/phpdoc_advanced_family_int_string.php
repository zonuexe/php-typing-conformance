<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyIntString;

/**
 * Integer-string and literal-string refinements
 *
 * `decimal-int-string`, `non-decimal-int-string`, `literal-string` and
 * `non-empty-literal-string` are refinements of `string`. Analyzers that model
 * them reject a literal that does not match; others fall back to plain
 * `string` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver decimal-int-string / non-decimal-int-string / literal-string / non-empty-literal-string
 */

/** @param decimal-int-string $value */
function acceptsDecimalIntString($value): void // T: decimal-int-string
{
}

/** @param literal-string $value */
function acceptsLiteralString($value): void // T: literal-string
{
}

/** @param non-empty-literal-string $value */
function acceptsNonEmptyLiteralString($value): void // T: non-empty-literal-string
{
}

acceptsDecimalIntString('123');
acceptsDecimalIntString('abc'); // E?: 'abc' is not a decimal-int-string

acceptsLiteralString('a literal');

function forwardsRuntimeString(string $value): void
{
    acceptsLiteralString($value); // E?: an arbitrary runtime string is not a literal-string
}

acceptsNonEmptyLiteralString('x');
acceptsNonEmptyLiteralString(''); // E?: '' is not a non-empty-literal-string
