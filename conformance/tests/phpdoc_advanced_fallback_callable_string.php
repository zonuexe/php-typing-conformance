<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackCallableString;

/**
 * `callable-string`
 *
 * A string that names something callable, so the refinement is only meaningful
 * for an analyzer that knows which function names exist. Analyzers that model
 * it reject a name nothing is declared under; others fall back to plain
 * `string` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `callable-string` resolves to string&CallableType
 */

/**
 * @return callable-string
 */
function returnsCallableString() // T: callable-string
{
    return 'strlen'; // V
}

function acceptsString(string $value): void
{
}

/**
 * @param callable-string $value
 */
function acceptsCallableString($value): void // T: callable-string
{
}

// A `callable-string` value always satisfies a native `string` parameter.
acceptsString(returnsCallableString()); // V

// The name of an existing function satisfies the parameter.
acceptsCallableString('strlen'); // V

// A name nothing is declared under does not.
acceptsCallableString('definitely_not_a_function'); // E?: an undeclared name is not a callable-string

// Neither does the name of a class, which is a string PHP will not call.
acceptsCallableString(\stdClass::class); // E?: a class-string is not a callable-string
