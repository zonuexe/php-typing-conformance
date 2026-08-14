<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertIfFalsePsalm;

/**
 * Conditional assertion `@psalm-assert-if-false`.
 *
 * The mirror of `@psalm-assert-if-true`: the guarantee holds in the branch
 * where the function returned `false`. Useful for "is invalid" predicates
 * that keep the positive case in the `else`.
 *
 * References:
 * - Psalm adding_assertions.md: `@psalm-assert-if-false`
 * - PHPStan phpdocs-basics / narrowing-types: assert-if-false
 */

/**
 * @psalm-assert-if-false int $value
 */
function isNotIntValue(mixed $value): bool // T: @psalm-assert-if-false
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
