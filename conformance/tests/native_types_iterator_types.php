<?php

declare(strict_types=1);

namespace Conformance\Tests\NativeTypesIteratorTypes;

/**
 * `ArrayIterator::current()` and `key()` keep the inferred element types
 * without an explicit `@extends ArrayIterator<int, int>`.
 *
 * Source: carthage-software/mago#2476 (1.57.0).
 *
 * References:
 * - https://github.com/carthage-software/mago/pull/2476
 */

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

function fromIntList(): void
{
    $iterator = new \ArrayIterator([1, 2, 3]);
    takesInt($iterator->current()); // V
    takesString($iterator->current()); // E?: current() is int
    takesInt($iterator->key()); // V: list keys are int
}

function fromStringList(): void
{
    $iterator = new \ArrayIterator(['a', 'b']);
    takesString($iterator->current()); // V
    takesInt($iterator->current()); // E?: current() is string
}
