<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyUtility;

/**
 * `key-of<T>` and `value-of<T>` utility types.
 *
 * `key-of<T>` is the union of an array shape's keys, `value-of<T>` the union of
 * its values. Analyzers that model them reject a value outside the union;
 * others fall back to `array-key`/`mixed` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver key-of / value-of
 */

/** @param key-of<array{name: string, age: int}> $key */
function acceptsShapeKey($key): void
{
}

/** @param value-of<array{a: int, b: int}> $value */
function acceptsShapeValue($value): void
{
}

acceptsShapeKey('name');
acceptsShapeKey('missing'); // E?: 'missing' is not a key of the shape

acceptsShapeValue(1);
acceptsShapeValue('x'); // E?: a string is not a value of array{a: int, b: int}
