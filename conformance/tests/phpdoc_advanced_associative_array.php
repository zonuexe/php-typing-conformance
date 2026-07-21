<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedAssociativeArray;

/**
 * `associative-array` means a non-list array in Phan, an alias elsewhere.
 *
 * Phan treats `associative-array<K, V>` as an array that is specifically not a
 * list, so a plain list argument is rejected. PHPStan and Psalm accept
 * `associative-array` only as a semantic alias of `array`, so they accept the
 * same list. This records that divergence in meaning.
 *
 * References:
 * - Phan Type/AssociativeArrayType.php (array that is not a list)
 * - PHPStan/Psalm treat `associative-array` as an `array` alias
 */

/**
 * @param associative-array<int, string> $map
 */
function acceptsAssoc(array $map): void
{
}

// A non-sequential int-keyed array is associative everywhere.
acceptsAssoc([5 => 'a', 9 => 'b']);

// A plain list has the same int-key/string-value element types, so alias-only
// analyzers accept it; Phan rejects it because a list is not associative.
acceptsAssoc(['a', 'b', 'c']); // E?: a list is not an associative-array (enforced by Phan)
