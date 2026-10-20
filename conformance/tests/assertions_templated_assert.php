<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsTemplatedAssert;

/**
 * `@psalm-assert T $actual` with a template taken from another argument.
 *
 * After `assertSame(1, $value)`, `$value` is `int` (the template pinned by
 * the first argument), so `takesInt` is valid and `takesString` is not.
 * Non-templated `@psalm-assert` is `phpdoc_advanced_vendor_prefixed_assert_psalm`.
 *
 * References:
 * - Psalm adding_assertions.md: @psalm-assert
 * - vimeo/psalm 3.0.0 templated assertions
 */

/**
 * @template T
 * @param T $expected
 * @psalm-assert T $actual
 * @phpstan-assert T $actual
 */
function assertSame(mixed $expected, mixed $actual): void // T: @psalm-assert
{
}

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

function fromIntLiteral(mixed $value): void
{
    assertSame(1, $value);
    takesInt($value); // Q: the template assertion pins $value to int
    echo \is_string($value) ? 'string' : 'int'; // E?: after assert int, is_string() is always false
}

function fromStringLiteral(mixed $value): void
{
    assertSame('x', $value);
    takesString($value); // Q: the template assertion pins $value to string
    echo \is_int($value) ? 'int' : 'string'; // E?: after assert string, is_int() is always false
}
