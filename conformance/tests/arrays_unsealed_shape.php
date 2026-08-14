<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysUnsealedShape;

/**
 * `array{foo: int, ...}`
 *
 * A trailing `...` unseals an array shape: the listed keys keep their declared
 * types, and any further key is admitted with an unconstrained value. The two
 * halves have to be probed separately, because an analyzer can get either one
 * wrong on its own — dropping the `...` turns every extra key into a false
 * positive, while dropping the shape lets a missing or mistyped `foo` through.
 *
 * References:
 * - Psalm "Unsealed array and list shapes", `...` as shorthand for `...<array-key, mixed>`
 */

/**
 * @param array{foo: int, ...} $shape
 */
function acceptsUnsealedShape(array $shape): void // T: array{foo: int, ...}
{
}

// The declared key on its own satisfies the shape.
acceptsUnsealedShape(['foo' => 1]); // V

// So does the declared key alongside undeclared ones of any type.
acceptsUnsealedShape(['foo' => 2, 'buz' => 42.0]); // V
acceptsUnsealedShape(['foo' => 3, 'buz' => 42.0, 'qux' => null]); // V

// `...` relaxes the undeclared keys only. `foo` is still required — an
// analyzer that widened the spelling to plain `array` accepts all three of
// these, so silence here says the shape was thrown away rather than honoured.
acceptsUnsealedShape([]); // E?: array{} does not have the required key foo
acceptsUnsealedShape(['buz' => 42.0]); // E?: an undeclared key does not stand in for foo

// and still an int.
acceptsUnsealedShape(['foo' => 'one']); // E?: foo is int, not string
