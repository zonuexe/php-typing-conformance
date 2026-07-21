<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPseudotypeClassPrecedence;

/**
 * A same-named class takes precedence over the `integer` pseudo-type keyword.
 *
 * When a class named `Integer` is in scope, `@param Integer` should resolve to
 * that class, not to the `int` keyword. Analyzers with this fallback reject a
 * plain `int` argument; analyzers that always read `integer` as `int` accept
 * it. The same rule applies to `boolean`, `double`, `number`, `resource`, etc.
 *
 * References:
 * - PHPStan TypeNodeResolver::tryResolvePseudoTypeClassType
 */

final class Integer
{
}

/**
 * @param Integer $value
 */
function acceptsIntegerClass($value): void
{
}

// An `Integer` instance satisfies the class-resolved parameter.
acceptsIntegerClass(new Integer());

// A plain int is not an `Integer` instance for analyzers that resolve the
// same-named class; keyword-only analyzers accept it as `int`.
acceptsIntegerClass(5); // E?: int should be rejected where @param Integer resolves to the class
