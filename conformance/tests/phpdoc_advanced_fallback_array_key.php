<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackArrayKey;

/**
 * `array-key`
 *
 * `array-key` is the union of the types PHP accepts as an array key,
 * `int|string`. Unlike the other pseudo-types in this group it refines no
 * single native type: both `int` and `string` satisfy it, while an `array-key`
 * value satisfies neither `int` nor `string` on its own. Analyzers that model
 * it reject a value PHP cannot use as a key; others fall back to `mixed` and
 * accept everything.
 *
 * The reverse direction separates the analyzers further. PHPStan resolves the
 * keyword to a *benevolent* union, which is deliberately accepted wherever
 * either member is expected, so passing an `array-key` to a native `string`
 * parameter stays silent there. Analyzers that resolve it to an ordinary
 * `int|string` — or to a dedicated array-key atom — report the half of the
 * union that does not fit.
 *
 * References:
 * - PHPStan TypeNodeResolver `array-key` resolves to BenevolentUnionType(int|string)
 * - Psalm docs, Type syntax / Scalar types: `array-key`
 */

/**
 * @return array-key
 */
function returnsArrayKey() // T: array-key
{
    return \random_int(0, 1) === 1 ? 1 : 'key';
}

function acceptsInt(int $value): void
{
}

function acceptsString(string $value): void
{
}

/**
 * @param array-key $value
 */
function acceptsArrayKey($value): void // T: array-key
{
}

// Both members of the union satisfy the parameter.
acceptsArrayKey(1);
acceptsArrayKey('key');

// Values PHP cannot use as an array key: analyzers that model `array-key`
// reject them, others fall back to `mixed` and accept them.
acceptsArrayKey(1.5); // E?: a float is not an array-key (int|string)
acceptsArrayKey(true); // E?: a bool is not an array-key (int|string)
acceptsArrayKey(null); // E?: null is not an array-key (int|string)

// The reverse direction: an `array-key` is not narrowed to either member, so a
// parameter that wants only one of them is not satisfied. PHPStan's benevolent
// union stays silent here by design.
acceptsInt(returnsArrayKey()); // E?: an array-key may be a string
acceptsString(returnsArrayKey()); // E?: an array-key may be an int
