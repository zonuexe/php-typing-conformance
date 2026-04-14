<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysNonEmptyList;

/**
 * Non-empty list constraints, analogous to Python tuple/list usage tests.
 *
 * References:
 * - PHPStan non-empty-list
 * - Psalm list and non-empty-list
 */

/**
 * @param non-empty-list<int> $values
 */
function takesNonEmptyList(array $values): void
{
}

takesNonEmptyList([1, 2, 3]);
takesNonEmptyList([]); // E: empty array is not accepted by non-empty-list<int>
