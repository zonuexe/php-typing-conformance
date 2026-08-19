<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanBenevolentUnion;

/**
 * `__benevolent<int|string>`
 *
 * A PHPStan-only wrapper: the inner union is accepted wherever *any* of its
 * members would be. Passing `__benevolent<int|string>` to a native `int` or
 * `string` parameter is silent; the same value typed as a plain `int|string`
 * is not. The inbound direction is unchanged — a float is still not in the
 * union. PHPStan uses the spelling in stubs (`__benevolent<CurlHandle|false>`);
 * `array-key` is the same constructor without the wrapper.
 *
 * Honouring the wrapper is silence on the member-assignment lines. Tools that
 * do not know the spelling reject those calls as an unknown class or as an
 * ordinary union; that is not enforcement of `__benevolent`.
 *
 * References:
 * - PHPStan TypeNodeResolver `__benevolent` → TypeUtils::toBenevolentUnion
 * - PHPStan config-reference: `checkBenevolentUnionTypes` (off by default)
 * - https://github.com/phpstan/phpstan/discussions/6440
 */

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

/**
 * @return __benevolent<int|string>
 */
function returnsBenevolent() // T: __benevolent<int|string>
{
    return \random_int(0, 1) === 1 ? 1 : 'key'; // V
}

/**
 * @return int|string
 */
function returnsUnion(): int|string
{
    return \random_int(0, 1) === 1 ? 1 : 'key';
}

/**
 * @param __benevolent<int|string> $value
 */
function acceptsBenevolent($value): void // T: __benevolent<int|string>
{
}

// Both members of the inner union satisfy the parameter.
acceptsBenevolent(1); // V
acceptsBenevolent('key'); // V

// Inbound is still the union: a float is not an int or a string.
acceptsBenevolent(1.5); // E?: a float is not in the union, benevolent or not

// Honour is silence: the wrapper is accepted as either member.
takesInt(returnsBenevolent()); // Q?<phpstan> // Q?<phpstan-strict> // E?[noise]
takesString(returnsBenevolent()); // Q?<phpstan> // Q?<phpstan-strict> // E?[noise]

// Contrast: a sound union is rejected at the same calls. If these were also
// silent, the Q lines above would not be measuring benevolence. Noise so the
// contrast does not count as `__benevolent` enforcement.
takesInt(returnsUnion()); // E?[noise]: a plain int|string may be a string
takesString(returnsUnion()); // E?[noise]: a plain int|string may be an int
