<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsIteratorToArray;

/**
 * `iterator_to_array()` keeps the iterator's key and value types.
 *
 * `iterator_to_array($it)` on an `Iterator<int, int>` is `array<int, int>`;
 * with `preserve_keys: false` the values become a `list`. Passing the result
 * where the other element type is required is the probe. The iterators are
 * declared parameters, so the measurement does not depend on inferring a
 * template from a constructor — that is
 * `generics_array_iterator_constructor_inference`.
 *
 * Source: jorgsowa/mir v0.86.0.
 *
 * References:
 * - https://github.com/jorgsowa/mir/releases/tag/v0.86.0
 */

/**
 * @param array<int, int> $array
 */
function takesIntMap(array $array): void
{
}

/**
 * @param list<string> $list
 */
function takesStringList(array $list): void
{
}

/**
 * @param \Iterator<int, int> $ok
 * @param \Iterator<int, int> $wrong
 */
function fromIntIterator(\Iterator $ok, \Iterator $wrong): void
{
    takesIntMap(iterator_to_array($ok)); // V
    takesStringList(iterator_to_array($wrong)); // E: values are int, not string
}

/**
 * @param \Iterator<int, string> $ok
 * @param \Iterator<int, string> $wrong
 */
function fromStringIteratorWithoutKeys(\Iterator $ok, \Iterator $wrong): void
{
    takesStringList(iterator_to_array($ok, preserve_keys: false)); // V
    takesIntMap(iterator_to_array($wrong, preserve_keys: false)); // E: values are string, not int
}
