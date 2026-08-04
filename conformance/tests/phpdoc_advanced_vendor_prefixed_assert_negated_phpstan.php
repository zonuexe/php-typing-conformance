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

function takesInt(int $value): void
{
}

function example(int|string $value): void
{
    assertNotInt($value);

    // After subtracting int, only string remains — passing to takesInt must fail.
    takesInt($value); // E?: after @phpstan-assert !int, int|string is string
}
