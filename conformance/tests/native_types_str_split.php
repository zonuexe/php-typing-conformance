<?php

declare(strict_types=1);

namespace Conformance\Tests\NativeTypesStrSplit;

/**
 * `str_split()` / `mb_str_split()` length must be `positive-int`.
 *
 * The interesting call is `str_split($s, -1)`: tools that model the stub
 * reject a non-positive length. Return non-emptiness is not probed here —
 * a `array` receiver would accept empty and non-empty alike.
 *
 * Source: vimeo/psalm#6324 (4.10.0).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/6324
 */

function takesString(string $value): void
{
}

$parts = str_split('hello', 2);
takesString($parts[0]); // V

str_split('hello', -1); // E?: length must be positive-int
str_split('hello', 0); // E?: 0 is not a positive-int

$mb = mb_str_split('hello', 2);
takesString($mb[0]); // V
mb_str_split('hello', -1); // E?: length must be positive-int
