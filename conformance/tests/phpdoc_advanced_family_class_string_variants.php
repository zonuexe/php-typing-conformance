<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyClassStringVariants;

/**
 * `class-string` variants fall back to `class-string`/`string`.
 *
 * `interface-string`, `trait-string` and `enum-string` constrain a
 * `class-string` to an interface, trait or enum respectively. Analyzers that
 * model them reject a class-string of the wrong kind; others fall back to
 * plain `class-string` or `string` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver interface-string / trait-string / enum-string
 */

interface SomeInterface
{
}

trait SomeTrait
{
}

enum SomeEnum
{
    case A;
}

final class SomeClass
{
}

/** @param interface-string $value */
function acceptsInterfaceString($value): void
{
}

/** @param trait-string $value */
function acceptsTraitString($value): void
{
}

/** @param enum-string $value */
function acceptsEnumString($value): void
{
}

acceptsInterfaceString(SomeInterface::class);
acceptsInterfaceString(SomeClass::class); // E?: a non-interface class-string is not an interface-string

acceptsTraitString(SomeTrait::class);
acceptsTraitString(SomeClass::class); // E?: a non-trait class-string is not a trait-string

acceptsEnumString(SomeEnum::class);
acceptsEnumString(SomeClass::class); // E?: a non-enum class-string is not an enum-string
