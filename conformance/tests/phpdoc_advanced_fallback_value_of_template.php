<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackValueOfTemplate;

/**
 * `value-of<T>` over a template parameter
 *
 * The value-side mirror of the template `key-of` case: a generic function
 * takes `T of array` and returns one of its values, so `value-of<T>` is
 * evaluated at the call site from the inferred T. Without the evaluation the
 * return is `mixed`, which satisfies everything silently — so the probe is a
 * parameter the projected value type cannot satisfy, and the precision probe
 * is a literal union only an unwidened T can satisfy.
 *
 * References:
 * - PHPStan phpdoc-types: value-of over template parameters
 * - Psalm utility_types.md: value-of with templates
 */

/**
 * @template T of array<array-key, mixed>
 * @param T $items
 * @return value-of<T>
 */
function firstValue(array $items) // T: value-of<T>
{
    foreach ($items as $value) {
        // A tool can evaluate the projection at call sites and still be unable
        // to prove this body produces value-of<T>; that limitation surfaces as
        // a false positive here, not as an expectation of the test.
        return $value;
    }

    throw new \InvalidArgumentException('empty array');
}

function acceptsInt(int $value): void
{
}

function acceptsString(string $value): void
{
}

/**
 * @param 1|10 $level
 */
function acceptsLevel(int $level): void
{
}

// With int values, the returned value is an int.
acceptsInt(firstValue(['low' => 1, 'high' => 10]));

// More precisely, it is the union of this argument's values: a tool that
// infers T but widens the values to `int` reports a false positive here.
acceptsLevel(firstValue(['low' => 1, 'high' => 10]));

// A value of this argument is never a string.
acceptsString(firstValue(['low' => 1, 'high' => 10])); // E?: the values of this argument are ints
