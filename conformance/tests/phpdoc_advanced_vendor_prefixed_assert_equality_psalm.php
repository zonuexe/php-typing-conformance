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
 * The true-branch probe checks that the `=` form is recognised and still
 * narrows. The false-branch `// Q` fails only when `=` is read as ordinary
 * `int` (is_int becomes always-false). Honouring equality and ignoring the
 * tag are both silent there.
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

function takesInt(int $value): void
{
}

function example(mixed $value): void
{
    if (equalsFive($value)) {
        takesInt($value); // V

        // True branch: equality still asserts int, so is_string is impossible.
        echo \is_string($value) ? 'string' : 'int'; // E?: in the true branch of =int, is_string() is always false
    } else {
        // False branch of =int must NOT force !int: `$value` may still be int.
        // Honouring `=` and ignoring the tag are both silent here. A tool that
        // treats `=int` as ordinary `int` flags is_int as always false — that
        // is the spec miss this quiet probe is for.
        echo \is_int($value) ? 'maybe-int' : 'other'; // Q: false branch of =int must not force !int
    }
}
