<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertIfFalsePhpStan;

/**
 * Conditional assertion `@phpstan-assert-if-false`.
 *
 * The PHPStan spelling of the same "holds when the call returns false"
 * conditional. Paired with the Psalm file under the other prefix.
 *
 * References:
 * - PHPStan narrowing-types.md: `@phpstan-assert-if-false`
 * - Psalm adding_assertions.md: assert-if-false
 */

/**
 * @phpstan-assert-if-false int $value
 */
function isNotIntValue(mixed $value): bool // T: @phpstan-assert-if-false
{
    return !\is_int($value);
}

function takesInt(int $value): void
{
}

function example(mixed $value): void
{
    if (isNotIntValue($value)) {
        // True branch: no assertion applied; `$value` stays mixed.
    } else {
        // False branch: the assertion makes `$value` an int.
        takesInt($value); // V
        echo \is_string($value) ? 'string' : 'int'; // E?: in the false branch, is_string() is always false
    }
}
