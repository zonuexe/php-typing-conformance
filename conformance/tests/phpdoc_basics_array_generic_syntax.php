<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocBasicsArrayGenericSyntax;

/**
 * Array element and list syntax in PHPDoc.
 *
 * `string[]`, `array<string>`, `array<int, string>`, and `list<string>` are
 * the everyday spellings. `arrays_list_element_type` already asks whether a
 * `list<int>` rejects strings; this file asks whether each syntax is resolved
 * at all.
 *
 * References:
 * - PHPStan array types
 * - Psalm array types
 */

/**
 * @param string[] $values
 */
function takesStringArray(array $values): void // T: string[]
{
}

/**
 * @param array<string> $values
 */
function takesGenericStringArray(array $values): void // T: array<string>
{
}

/**
 * @param array<int, string> $values
 */
function takesIntegerStringMap(array $values): void // T: array<int, string>
{
}

/**
 * @param list<string> $values
 */
function takesStringList(array $values): void // T: list<string>
{
}

takesStringArray(['one', 'two']); // V
takesGenericStringArray(['one', 'two']); // V
takesIntegerStringMap([1 => 'one', 2 => 'two']); // V
takesStringList(['one', 'two']); // V

takesStringArray([1]); // E?: an array of integers is not an array of strings
takesGenericStringArray([1]); // E?: the generic array value type is string
takesIntegerStringMap(['one' => 'one']); // E?: the generic array key type is int
takesStringList([1]); // E?: a list of integers is not a list of strings
