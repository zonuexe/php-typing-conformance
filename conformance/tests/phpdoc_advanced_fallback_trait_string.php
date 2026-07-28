<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackTraitString;

/**
 * `trait-string`
 *
 * A `class-string` narrowed to the name of a trait. Traits are the odd member
 * of the family: the name exists at compile time and `::class` resolves it, but
 * it denotes nothing instantiable, so an analyzer that checks "is this a known
 * class" rather than "is this a known trait" lets an ordinary class name
 * through.
 *
 * References:
 * - PHPStan TypeNodeResolver `trait-string` resolves to ClassStringType
 */

trait SomeTrait
{
}

final class SomeClass
{
    use SomeTrait;
}

/**
 * @return trait-string
 */
function returnsTraitString() // T: trait-string
{
    return SomeTrait::class;
}

function acceptsString(string $value): void
{
}

/**
 * @param trait-string $value
 */
function acceptsTraitString($value): void // T: trait-string
{
}

// A `trait-string` value always satisfies a native `string` parameter.
acceptsString(returnsTraitString());

// The name of a trait satisfies the parameter.
acceptsTraitString(SomeTrait::class);

// The name of a class that uses it does not.
acceptsTraitString(SomeClass::class); // E?: a class-string is not a trait-string
