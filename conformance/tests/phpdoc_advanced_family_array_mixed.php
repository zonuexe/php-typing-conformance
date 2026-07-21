<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyArrayMixed;

/**
 * `non-empty-array`, `non-empty-mixed` and `array-key` refinements.
 *
 * `non-empty-array` is an array with at least one element, `non-empty-mixed` is
 * `mixed` excluding falsy values, and `array-key` is `int|string`. Analyzers
 * that model them reject a value outside the refinement; others fall back to
 * the base type and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver non-empty-array / non-empty-mixed / array-key
 */

/** @param non-empty-array<mixed> $value */
function acceptsNonEmptyArray(array $value): void
{
}

/** @param non-empty-mixed $value */
function acceptsNonEmptyMixed($value): void
{
}

/** @param array-key $value */
function acceptsArrayKey($value): void
{
}

acceptsNonEmptyArray([1]);
acceptsNonEmptyArray([]); // E?: an empty array is not a non-empty-array

acceptsNonEmptyMixed(1);
acceptsNonEmptyMixed(''); // E?: '' (falsy) is excluded from non-empty-mixed

acceptsArrayKey(1);
acceptsArrayKey('key');
acceptsArrayKey(1.5); // E?: a float is not an array-key (int|string)
