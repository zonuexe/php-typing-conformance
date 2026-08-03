<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsUnionOfArraysVsArrayOfUnions;

/**
 * Union of homogeneous arrays is not an array of unions.
 *
 * `@param array<int>|array<string>` means "all ints, or all strings" — not
 * "each element may be int or string". A mixed literal such as `[1, 'b', 3]`
 * is therefore out of range: it is neither `array<int>` nor `array<string>`.
 * By contrast `array<int|string>` would accept the same value.
 *
 * PHPStan historically accepted the mixed array (issue #8963, open as a
 * feature request). This records whether each analyzer distinguishes the two
 * spellings.
 *
 * Reference: https://github.com/phpstan/phpstan/issues/8963
 */

/**
 * @param array<int>|array<string> $values
 */
function takesHomogeneousIntOrStringArray(array $values): void // E<noverify>: NoVerify cannot evaluate the generic array union and flags the signature itself
{
}

// Pure int list — member of array<int>.
takesHomogeneousIntOrStringArray([1, 2, 3]);

// Pure string list — member of array<string>.
takesHomogeneousIntOrStringArray(['a', 'b', 'c']);

// Mixed elements: neither array<int> nor array<string>.
takesHomogeneousIntOrStringArray([1, 'b', 3]); // E: array<int|string> is not accepted by array<int>|array<string>
