<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocBasicsIterableGenericSyntax;

/**
 * Generic iterable syntax in PHPDoc.
 *
 * References:
 * - PHPStan iterable types
 * - Psalm iterable types
 */

/**
 * @param iterable<string> $values
 */
function takesStringIterable(iterable $values): void // T: iterable<string>
{
}

/**
 * @param iterable<int, string> $values
 */
function takesIntegerStringIterable(iterable $values): void // T: iterable<int, string>
{
}

takesStringIterable(['one', 'two']); // V
takesIntegerStringIterable([1 => 'one', 2 => 'two']); // V

takesStringIterable([1]); // E?: an iterable of integers is not an iterable of strings
takesIntegerStringIterable(['one' => 'one']); // E?: the iterable key type is int
