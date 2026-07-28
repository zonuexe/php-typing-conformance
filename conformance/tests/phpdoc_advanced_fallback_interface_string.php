<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackInterfaceString;

/**
 * `interface-string`
 *
 * A `class-string` narrowed to the name of an interface. Every probe here is a
 * valid `class-string`, so an analyzer that resolves the spelling but keeps
 * treating it as `class-string` accepts all of them — the refinement is only
 * doing work if the name of an ordinary class is rejected.
 *
 * References:
 * - PHPStan TypeNodeResolver `interface-string` resolves to ClassStringType
 */

interface SomeInterface
{
}

final class SomeClass implements SomeInterface
{
}

/**
 * @return interface-string
 */
function returnsInterfaceString() // T: interface-string
{
    return SomeInterface::class;
}

function acceptsString(string $value): void
{
}

/**
 * @param interface-string $value
 */
function acceptsInterfaceString($value): void // T: interface-string
{
}

// An `interface-string` value always satisfies a native `string` parameter.
acceptsString(returnsInterfaceString());

// The name of an interface satisfies the parameter.
acceptsInterfaceString(SomeInterface::class);

// The name of a class that implements it does not: the constraint is on the
// name, not on the type it denotes.
acceptsInterfaceString(SomeClass::class); // E?: a class-string is not an interface-string
