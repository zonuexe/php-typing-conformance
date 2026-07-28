<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackKeyOf;

/**
 * `key-of<T>`
 *
 * The union of an array shape's keys, computed from another type rather than
 * written out. That makes it the first spelling in this group an analyzer has
 * to *evaluate*: recognising the keyword is not enough, it has to read the
 * shape and derive `'name'|'age'`. Analyzers that stop at recognition fall back
 * to `array-key` and accept any key.
 *
 * References:
 * - PHPStan TypeNodeResolver `key-of` resolves through Type::getKeysArray()
 */

/**
 * @return key-of<array{name: string, age: int}>
 */
function returnsShapeKey() // T: key-of<array{name: string, age: int}>
{
    return \random_int(0, 1) === 1 ? 'name' : 'age';
}

function acceptsArrayKeyNatively(int|string $value): void
{
}

/**
 * @param key-of<array{name: string, age: int}> $key
 */
function acceptsShapeKey($key): void // T: key-of<array{name: string, age: int}>
{
}

// A key of the shape is an `int|string` whichever key it is.
acceptsArrayKeyNatively(returnsShapeKey());

// Both keys of the shape satisfy the parameter.
acceptsShapeKey('name');
acceptsShapeKey('age');

// A string that is not one of them does not.
acceptsShapeKey('missing'); // E?: 'missing' is not a key of array{name: string, age: int}

// Neither does one of the shape's *values*, which is the mistake the spelling
// invites.
acceptsShapeKey(0); // E?: 0 is not a key of array{name: string, age: int}
