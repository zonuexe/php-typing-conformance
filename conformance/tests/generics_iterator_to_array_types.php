<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsIteratorToArrayTypes;

/**
 * `iterator_to_array()` keeps the iterator's key and value types.
 *
 * `iterator_to_array($it)` on `ArrayIterator<int, int>` is `array<int, int>`;
 * with `preserve_keys: false` the values become a `list`. Passing the result
 * where the other element type is required is the probe.
 *
 * Source: jorgsowa/mir v0.86.0.
 *
 * References:
 * - https://github.com/jorgsowa/mir/releases/tag/v0.86.0
 */

/**
 * @param array<int, int> $array
 */
function takesIntMap(array $array): void // T: array<int, int>
{
}

/**
 * @param list<string> $list
 */
function takesStringList(array $list): void // T: list<string>
{
}

function fromIntIterator(): void
{
    $ok = new \ArrayIterator([1, 2, 3]);
    takesIntMap(iterator_to_array($ok)); // V
    $wrong = new \ArrayIterator([1, 2, 3]);
    takesStringList(iterator_to_array($wrong)); // E?: values are int, not string
}

function fromStringIteratorWithoutKeys(): void
{
    $ok = new \ArrayIterator(['a', 'b', 'c']);
    takesStringList(iterator_to_array($ok, preserve_keys: false)); // V
    $wrong = new \ArrayIterator(['a', 'b', 'c']);
    takesIntMap(iterator_to_array($wrong, preserve_keys: false)); // E?: values are string, not int
}
