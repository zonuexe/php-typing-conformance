<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertEqualityPhpStan;

/**
 * Equality assertion form `@phpstan-assert-if-true =Type`.
 *
 * PHPStan documents `=` as disabling false-branch narrowing when a
 * predicate can fail for reasons other than "not this type" (for example
 * an `isAdmin()` that also checks `active`). Paired with the Psalm file
 * under the other prefix.
 *
 * References:
 * - PHPStan narrowing-types.md: The `=` operator
 * - Psalm assertion_syntax.md: Equality assertions
 */

/**
 * @phpstan-assert-if-true =int $value
 */
function equalsFive(mixed $value): bool // T: @phpstan-assert-if-true =int
{
    return \is_int($value) && $value === 5;
}

function example(mixed $value): void
{
    if (equalsFive($value)) {
        // True branch: equality still asserts int, so is_string is impossible.
        echo \is_string($value) ? 'string' : 'int'; // E?: in the true branch of =int, is_string() is always false
    } else {
        // False branch of =int must NOT force !int: `$value` may still be int.
        echo \is_int($value) ? 'maybe-int' : 'other';
    }
}
