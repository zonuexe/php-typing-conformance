<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonEmptyArray;

/**
 * `non-empty-array`
 *
 * An array with at least one element. It is the array counterpart of
 * `non-empty-string`, and the only member of this group whose base type can be
 * written natively, so the parameter can carry `array` in the signature and the
 * refinement in the docblock at once.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-empty-array` resolves to array&NonEmptyArrayType
 */

/**
 * @return non-empty-array<mixed>
 */
function returnsNonEmptyArray(): array // T: non-empty-array<mixed>
{
    return [1]; // V
}

/**
 * @param array<mixed> $value
 */
function acceptsArray(array $value): void
{
}

/**
 * @param non-empty-array<mixed> $value
 */
function acceptsNonEmptyArray(array $value): void // T: non-empty-array<mixed>
{
}

// A `non-empty-array` value always satisfies a native `array` parameter.
acceptsArray(returnsNonEmptyArray()); // V

// One element is enough, whatever it is.
acceptsNonEmptyArray([1]); // V
acceptsNonEmptyArray([null]); // V

// The empty array is not.
acceptsNonEmptyArray([]); // E?: [] is not a non-empty-array
