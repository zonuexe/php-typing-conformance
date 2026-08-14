<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackValueOf;

/**
 * `value-of<T>`
 *
 * The union of an array shape's value types, derived the same way `key-of<T>`
 * derives its keys. An analyzer that recognises the keyword without evaluating
 * it falls back to `mixed`, which accepts everything — so unlike `key-of<T>`
 * there is no narrower fallback to hide behind.
 *
 * References:
 * - PHPStan TypeNodeResolver `value-of` resolves through Type::getValuesArray()
 */

/**
 * @return value-of<array{a: int, b: int}>
 */
function returnsShapeValue() // T: value-of<array{a: int, b: int}>
{
    return 1; // V
}

function acceptsInt(int $value): void
{
}

/**
 * @param value-of<array{a: int, b: int}> $value
 */
function acceptsShapeValue($value): void // T: value-of<array{a: int, b: int}>
{
}

// Every value of this shape is an `int`, so the derived type satisfies `int`.
acceptsInt(returnsShapeValue()); // V

// An int satisfies the parameter.
acceptsShapeValue(1); // V

// A string does not, however much it looks like one of the shape's keys.
acceptsShapeValue('x'); // E?: a string is not a value of array{a: int, b: int}
acceptsShapeValue('a'); // E?: 'a' is a key of the shape, not a value of it
