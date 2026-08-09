<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertNegatedPhpStan;

/**
 * Negated assertion form `@phpstan-assert !Type`.
 *
 * The PHPStan spelling of the same subtraction operator documented for
 * Psalm. A tool that honours one vendor's `!` and not the other is choosing
 * which dialect to read.
 *
 * References:
 * - PHPStan narrowing-types.md: Type negation (`@phpstan-assert !string`)
 * - Psalm assertion_syntax.md: Negated assertions
 */

/**
 * @phpstan-assert !int $value
 */
function assertNotInt(int|string $value): void // T: @phpstan-assert !int
{
    if (\is_int($value)) {
        throw new \InvalidArgumentException('must not be int');
    }
}

function example(int|string $value): void
{
    assertNotInt($value);

    // After subtracting int, only string remains, so an is_int() check is
    // provably dead. A tool that ignores the tag (or parses `!int` as an
    // unknown type) leaves the union intact, where is_int() is a live check
    // and nothing is reported — so the diagnostic marks real enforcement.
    echo \is_int($value) ? 'int' : 'string'; // E?: after @phpstan-assert !int, is_int() is always false
}
