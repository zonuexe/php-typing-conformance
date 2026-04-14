<?php

declare(strict_types=1);

namespace Conformance\Tests\HistoricalNullableArrayDefaultNull;

/**
 * Historical compatibility for legacy nullable-via-default patterns.
 *
 * References:
 * - python-typing historical/deprecated groups inspiration
 * - legacy PHP ecosystem usage before explicit nullable types were common
 */

/**
 * @param array<int>|null $values
 */
function legacyNullableArray(?array $values = null): void
{
}

legacyNullableArray();
legacyNullableArray([1, 2, 3]);
