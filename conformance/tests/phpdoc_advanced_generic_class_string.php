<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedGenericClassString;

/**
 * Generic `class-string` and `interface-string` refinements.
 *
 * Plain `class-string` is `phpdoc_advanced_fallback_class_string`. This file
 * asks whether a type argument is applied (`class-string<Contract>`) and
 * whether `interface-string` is a distinct spelling.
 *
 * References:
 * - PHPStan class-string and interface-string types
 * - Psalm class-string and interface-string types
 */

interface Contract
{
}

final class Implementation implements Contract
{
}

final class Unrelated
{
}

/**
 * @param class-string<Contract> $class
 */
function takesContractClassString($class): void // T: class-string<Contract>
{
}

/**
 * @param interface-string<Contract> $interface
 */
function takesContractInterfaceString($interface): void // T: interface-string<Contract>
{
}

takesContractClassString(Contract::class); // V
takesContractClassString(Implementation::class); // V
takesContractInterfaceString(Contract::class); // V

takesContractClassString(Unrelated::class); // E?: the class does not implement Contract
takesContractInterfaceString(Implementation::class); // E?: the name denotes a class, not an interface
takesContractClassString('not a class name'); // E?: a non-class string is not a class-string
