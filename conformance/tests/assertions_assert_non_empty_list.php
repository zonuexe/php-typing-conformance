<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsAssertNonEmptyList;

/**
 * Assertion-driven narrowing to non-empty list.
 *
 * References:
 * - PHP assert()
 * - analyzer support for assert-driven narrowing
 * - python-typing narrowing inspiration
 */

/**
 * @param list<int> $values
 */
function takesAtLeastOne(array $values): int
{
    assert($values !== []);

    return $values[0];
}

/**
 * @param list<int> $values
 */
function rejectsEmptyBranch(array $values): int
{
    if ($values === []) {
        return $values[0]; // E: empty-list branch should not permit index 0
    }

    return $values[0];
}
