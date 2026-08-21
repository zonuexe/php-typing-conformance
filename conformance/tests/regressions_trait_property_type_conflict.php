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
 * Tools that only complain about a missing constructor (Psalm, pzoom, mir) are
 * still a hit for this row: they saw the composition, just not the incompatibility.
 *
 * Source lead: Mago analyzer test `trait_property_type_conflicts`.
 */

trait LeftTrait
{
    public string $prop; // E[conflict]: string-vs-int property clash, or a related finding on the composed class // E<psalm>: reports only MissingConstructor for the uninitialized $prop, not the type conflict // E<phan>: PhanIncompatibleRealPropertyType — detects the string-vs-int conflict
}

trait RightTrait
{
    public int $prop; // E[conflict]
}

final class Composed // E[conflict] // E<pzoom>: MissingConstructor on the composed class, same finding as Psalm // E<mir>: same MissingConstructor
{
    use LeftTrait; // E[conflict]
    use RightTrait; // E[conflict] // E<mago>: incompatible-property-type — detects the conflict
}
