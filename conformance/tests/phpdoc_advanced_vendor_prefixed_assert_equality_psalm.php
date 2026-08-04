<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertEqualityPsalm;

/**
 * Equality assertion form `@psalm-assert-if-true =Type`.
 *
 * Prefixing the asserted type with `=` changes two things versus a plain
 * type assertion:
 *
 * 1. The true branch still narrows (here `$value` becomes `int`).
 * 2. The false branch does *not* get the negation — `$value` may still be
 *    an int. A plain `int` assertion would make the false branch `!int`.
 *
 * The true-branch probe below checks that the `=` form is recognised and
 * still narrows. The false branch is left unprobed as a required diagnostic
 * because tools that ignore the tag and tools that honour equality look the
 * same there (no "always false" noise either way).
 *
 * References:
 * - Psalm assertion_syntax.md: Equality assertions (`=int`)
 * - PHPStan narrowing-types.md: The `=` operator
 */

/**
 * @psalm-assert-if-true =int $value
 */
function equalsFive(mixed $value): bool // T: @psalm-assert-if-true =int
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
        // A tool that treats `=int` as plain `int` would flag is_int as always
        // false here; tools that honour equality (or ignore the tag) stay quiet.
        echo \is_int($value) ? 'maybe-int' : 'other';
    }
}
