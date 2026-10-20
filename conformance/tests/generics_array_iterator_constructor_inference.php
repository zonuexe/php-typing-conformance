<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsArrayIteratorConstructorInference;

/**
 * `ArrayIterator`'s template is inferred from its constructor argument.
 *
 * `new ArrayIterator([1, 2, 3])` is `ArrayIterator<int, int>` with no
 * annotation at the call site. `current()` and `key()` return null at an
 * invalid position, so the receivers are nullable: the probe is the element
 * type, not the null.
 * `generics_iterator_to_array` consumes a declared iterator instead, so the
 * two rows separate the inference from the function signature.
 *
 * Source: carthage-software/mago#2476 (1.57.0).
 *
 * References:
 * - https://github.com/carthage-software/mago/pull/2476
 */

function takesNullableInt(?int $value): void
{
}

function takesNullableString(?string $value): void
{
}

function fromIntList(): void
{
    $iterator = new \ArrayIterator([1, 2, 3]);
    takesNullableInt($iterator->current()); // V
    takesNullableString($iterator->current()); // E: current() is int|null
    takesNullableInt($iterator->key()); // V: list keys are int
}

function fromStringList(): void
{
    $iterator = new \ArrayIterator(['a', 'b']);
    takesNullableString($iterator->current()); // V
    takesNullableInt($iterator->current()); // E: current() is string|null
}
