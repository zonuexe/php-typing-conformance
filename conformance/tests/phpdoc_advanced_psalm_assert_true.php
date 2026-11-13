<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmAssertTrue;

/**
 * Bool assertion form `@psalm-assert true $bar`.
 *
 * After the call, `$value` is the `true` literal, so `=== false` is dead.
 * Tools that ignore the tag leave `bool`, where the comparison is live.
 *
 * References:
 * - Psalm assertion_syntax.md: Bool assertions (`true`)
 */

/**
 * @psalm-assert true $value
 */
function assertTrue(bool $value): void // T: @psalm-assert true
{
    if ($value !== true) {
        throw new \InvalidArgumentException('not true');
    }
}

function takesTrue(true $value): void
{
}

function example(bool $value): void
{
    assertTrue($value);

    takesTrue($value); // V

    echo $value === false ? 'false' : 'true'; // E?: after @psalm-assert true, === false is always false
}
