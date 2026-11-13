<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmAssertNotFalse;

/**
 * Negated bool assertion `@psalm-assert !false $bar`.
 *
 * Starting from `bool|int`, subtracting `false` leaves `true|int`, so
 * `=== false` is dead. A tool that parses `!false` as an unknown type (or
 * ignores the tag) leaves the union, where the comparison is live.
 *
 * Distinct from `@psalm-assert true`: `!false` on `bool|int` keeps `int`.
 *
 * References:
 * - Psalm assertion_syntax.md: Bool assertions (`!false`)
 */

/**
 * @psalm-assert !false $value
 */
function assertNotFalse(bool|int $value): void // T: @psalm-assert !false
{
    if ($value === false) {
        throw new \InvalidArgumentException('must not be false');
    }
}

function takesBoolOrInt(bool|int $value): void
{
}

function example(bool|int $value): void
{
    assertNotFalse($value);

    takesBoolOrInt($value); // V

    echo $value === false ? 'false' : 'other'; // E?: after @psalm-assert !false, === false is always false
}
