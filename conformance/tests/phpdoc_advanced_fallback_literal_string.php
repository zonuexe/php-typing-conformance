<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackLiteralString;

/**
 * `literal-string`
 *
 * A string whose every character comes from source code rather than from
 * runtime input — the refinement injection-sensitive APIs ask for. Concatenating
 * two literals keeps it literal; mixing in a value of unknown provenance does
 * not. Analyzers that model it reject a runtime string; others fall back to
 * plain `string` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `literal-string` resolves to string&AccessoryLiteralStringType
 */

/**
 * @return literal-string
 */
function returnsLiteralString() // T: literal-string
{
    return 'literal';
}

function acceptsString(string $value): void
{
}

/**
 * @param literal-string $value
 */
function acceptsLiteralString($value): void // T: literal-string
{
}

// A `literal-string` value always satisfies a native `string` parameter.
acceptsString(returnsLiteralString());

// Source-code literals satisfy the parameter, including a concatenation of two
// of them and the empty string.
acceptsLiteralString('a literal');
acceptsLiteralString('a' . ' literal');
acceptsLiteralString('');

function forwardsRuntimeString(string $value): void
{
    // A string of unknown provenance: enforcing analyzers reject it, others
    // fall back to `string` and accept it.
    acceptsLiteralString($value); // E?: an arbitrary runtime string is not a literal-string

    // Concatenating a literal onto it does not launder it either.
    acceptsLiteralString('prefix ' . $value); // E?: a literal concatenated with a runtime string is not a literal-string
}
