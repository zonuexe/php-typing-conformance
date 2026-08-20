<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsArrayKeyExistsKeyNarrowing;

/**
 * `array_key_exists($key, $map)` narrows `$key` to the keys `$map` is known
 * to have, not only the offset access after the check.
 *
 * A `int|string` key that is known to exist in `array<string, int>` is a
 * `string`. Passing it to a `string` sink is silent after the guard and
 * rejected without it. The same intersection applies to a literal map
 * `['a' => 1, 'b' => 2]`: a `string` key becomes `'a'|'b'`.
 *
 * Source: carthage-software/mago#2199
 *
 * References:
 * - PHP `array_key_exists()`
 * - https://github.com/carthage-software/mago/issues/2199
 */

function takesString(string $value): void
{
}

/**
 * @param 'a'|'b' $key
 */
function takesAB(string $key): void // E?[noise]: some tools do not parse 'a'|'b'
{
}

/**
 * @param array<string, int> $values
 */
function genericStringKeys(int|string $key, array $values): void // E?[noise]: some tools do not parse array<string, int>
{
    if (array_key_exists($key, $values)) {
        takesString($key); // Q: $key is string after the guard
    }

    takesString($key); // E: int|string is not string
}

/**
 * @param array<string, int> $values
 */
function alreadyString(string $key, array $values): void // E?[noise]: some tools do not parse array<string, int>
{
    if (array_key_exists($key, $values)) {
        takesString($key); // V: $key was already string
    }
}

function knownStringKeys(string $key): void
{
    $values = ['a' => 1, 'b' => 2];

    if (array_key_exists($key, $values)) {
        takesAB($key); // Q?: $key is 'a'|'b' after the guard
    }

    takesAB($key); // E?: string is not 'a'|'b'
}

takesAB('a'); // V
takesAB('b'); // V
