<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhanNonEmptyAssociativeArray;

/**
 * `non-empty-associative-array` is a Phan-only array refinement.
 *
 * It combines two Phan refinements: the array must be non-empty and must not be
 * a list. Phan models both; other analyzers do not recognize the keyword and
 * fall back to plain `array`, accepting an empty array or a list.
 *
 * References:
 * - Phan Type/NonEmptyAssociativeArrayType.php
 */

/**
 * @param non-empty-associative-array<string, int> $map
 */
function acceptsNonEmptyAssoc(array $map): void // T: non-empty-associative-array<string, int>
{
}

// A non-empty string-keyed array satisfies the parameter.
acceptsNonEmptyAssoc(['a' => 1]);

// An empty array violates the non-empty part.
acceptsNonEmptyAssoc([]); // E?: an empty array is not a non-empty-associative-array

// A list violates the associative part.
acceptsNonEmptyAssoc([1, 2, 3]); // E?: a list is not an associative array
