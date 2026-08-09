<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertNegatedPsalm;

/**
 * Negated assertion form `@psalm-assert !Type`.
 *
 * Unlike a positive `@psalm-assert int $value` (which *adds* a type), the
 * `!` prefix *subtracts* one. Starting from `int|string`, after the call
 * `$value` is known to be `string`. Tools that parse `!int` as an unknown
 * type name (or ignore the tag) leave the union alone.
 *
 * Note: probing `mixed` with `is_int()` after `!int` is a weak signal —
 * Psalm does not treat `mixed \ int` as "is_int always false". A union with
 * a concrete alternative is the documented shape that surfaces subtraction.
 *
 * References:
 * - Psalm assertion_syntax.md: Negated assertions
 * - Psalm adding_assertions.md: `@psalm-assert !null $value`
 * - PHPStan narrowing-types.md: Type negation (`!string`)
 */

/**
 * @psalm-assert !int $value
 */
function assertNotInt(int|string $value): void // T: @psalm-assert !int
{
    if (\is_int($value)) {
        throw new \InvalidArgumentException('must not be int');
    }
}

function example(int|string $value): void
{
    assertNotInt($value);

    // After subtracting int, only string remains, so an is_int() check is
    // provably dead. A tool that parses `!int` as an unknown type name (or
    // ignores the tag) leaves the union intact, where is_int() is a live check
    // and nothing is reported — so the diagnostic marks real enforcement.
    echo \is_int($value) ? 'int' : 'string'; // E?: after @psalm-assert !int, is_int() is always false
}
