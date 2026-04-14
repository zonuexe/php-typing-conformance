<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysListElementType;

/**
 * List element type compatibility checks.
 *
 * References:
 * - PHPStan list<T>
 * - Psalm list<T>
 * - python-typing list element compatibility inspiration
 */

/**
 * @param list<int> $values
 */
function takesIntList(array $values): void
{
}

takesIntList([1, 2, 3]);
takesIntList(['x']); // E: list<string> is not accepted where list<int> is required
