<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsTraitPropertyTypeConflict;

/**
 * Conflicting property types composed from two traits.
 *
 * `LeftTrait::$prop` is `string` and `RightTrait::$prop` is `int`. Composing both into one
 * class is a compile-time fatal error in PHP ("traits define the same property … the
 * definition differs and is considered incompatible"). Analyzers diverge on whether they
 * report this statically. PHPStan is silent even though the program cannot run.
 *
 * Source lead: Mago analyzer test `trait_property_type_conflicts`.
 */

trait LeftTrait
{
    public string $prop; // E<psalm>: reports only MissingConstructor for the uninitialized $prop, not the type conflict // E<phan>: PhanIncompatibleRealPropertyType — detects the string-vs-int conflict
}

trait RightTrait
{
    public int $prop;
}

final class Composed
{
    use LeftTrait;
    use RightTrait; // E<mago>: incompatible-property-type — detects the conflict. PHPStan is silent on both the conflict and the missing constructor, even though PHP fatals at compile time
}
