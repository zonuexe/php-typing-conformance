<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackCallableArray;

/**
 * `callable-array`
 *
 * The two-element `[$object, 'method']` form PHP accepts as a callable. It asks
 * more of an analyzer than the other callable refinements: recognising it means
 * checking the shape of the array *and* resolving the method name against the
 * first element's class. Analyzers that model it reject an array that is merely
 * a pair of values; others fall back to plain `array` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `callable-array` resolves to array&CallableType
 */

final class Greeter
{
    public function greet(): void
    {
    }
}

/**
 * @return callable-array
 */
function returnsCallableArray() // T: callable-array
{
    return [new Greeter(), 'greet']; // V
}

/**
 * @param array<mixed> $value
 */
function acceptsArray(array $value): void
{
}

/**
 * @param callable-array $value
 */
function acceptsCallableArray($value): void // T: callable-array
{
}

// A `callable-array` value always satisfies a native `array` parameter.
acceptsArray(returnsCallableArray()); // V

// The object/method pair satisfies the parameter.
acceptsCallableArray([new Greeter(), 'greet']); // V

// A pair naming a method that does not exist does not.
acceptsCallableArray([new Greeter(), 'missing']); // E?: the method does not exist, so this is not a callable-array

// Nor does an array that is simply two values.
acceptsCallableArray([1, 2]); // E?: [1, 2] is not a callable-array
