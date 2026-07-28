<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackEnumString;

/**
 * `enum-string`
 *
 * A `class-string` narrowed to the name of an enum. An enum is a class as far
 * as the engine is concerned, so an analyzer that only checks class existence
 * accepts any class name here; modelling the spelling means knowing which
 * declarations are enums.
 *
 * References:
 * - PHPStan TypeNodeResolver `enum-string` resolves to ClassStringType
 */

enum SomeEnum
{
    case A;
}

final class SomeClass
{
}

/**
 * @return enum-string
 */
function returnsEnumString() // T: enum-string
{
    return SomeEnum::class;
}

function acceptsString(string $value): void
{
}

/**
 * @param enum-string $value
 */
function acceptsEnumString($value): void // T: enum-string
{
}

// An `enum-string` value always satisfies a native `string` parameter.
acceptsString(returnsEnumString());

// The name of an enum satisfies the parameter.
acceptsEnumString(SomeEnum::class);

// The name of an ordinary class does not.
acceptsEnumString(SomeClass::class); // E?: a class-string is not an enum-string
