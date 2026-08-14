<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackScalar;

/**
 * `scalar`
 *
 * `int|float|string|bool` — the four types that are not arrays, objects, null
 * or resources. PHPStan reads the bare word as a class first and only falls
 * back to the union when no such class is in scope, so the spelling is one of
 * the few whose meaning depends on what else the file declares.
 *
 * References:
 * - PHPStan TypeNodeResolver `scalar` resolves to int|float|string|bool, after trying a pseudo-type class
 */

/**
 * @return scalar
 */
function returnsScalar() // T: scalar
{
    $values = [1, 1.5, 'x', true];

    return $values[\array_rand($values)]; // V
}

/**
 * @param scalar $value
 */
function acceptsScalar($value): void // T: scalar
{
}

// The spelling round-trips: what `@return scalar` produces is what `@param
// scalar` accepts.
acceptsScalar(returnsScalar()); // V

// All four members satisfy the parameter.
acceptsScalar(1); // V
acceptsScalar(1.5); // V
acceptsScalar('x'); // V
acceptsScalar(true); // V

// Everything else is outside the union.
acceptsScalar([1, 2]); // E?: an array is not a scalar
acceptsScalar(new \stdClass()); // E?: an object is not a scalar
acceptsScalar(null); // E?: null is not a scalar
