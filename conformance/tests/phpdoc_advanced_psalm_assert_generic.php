<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmAssertGeneric;

/**
 * Generic assertion form `@psalm-assert array<int, string> $foo`.
 *
 * After the call, values are `string`, so an `is_int` check on an element is
 * dead. Tools that parse the tag as a plain `array` (or ignore it) leave the
 * element type open, where `is_int` is a live check.
 *
 * References:
 * - Psalm assertion_syntax.md: Generic assertions
 */

/**
 * @psalm-assert array<int, string> $value
 */
function assertIntStringMap(mixed $value): void // T: @psalm-assert array<int, string>
{
    if (!\is_array($value)) {
        throw new \InvalidArgumentException('not array');
    }
}

function takesString(string $value): void
{
}

function example(mixed $value): void
{
    assertIntStringMap($value);

    takesString($value[0]); // V

    echo \is_int($value[0]) ? 'int' : 'string'; // E?: after array<int, string>, is_int() on a value is always false
}
