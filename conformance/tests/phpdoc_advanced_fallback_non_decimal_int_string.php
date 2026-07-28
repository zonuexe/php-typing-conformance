<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonDecimalIntString;

/**
 * `non-decimal-int-string`
 *
 * The complement of `decimal-int-string`, and wider than the name suggests: it
 * is every string that stays a string when used as an array key, so `'+1'`,
 * `'00'`, `'18E+3'`, `'1.2'` and plain `'foo'` all qualify. Only canonical
 * decimal notation is excluded. Analyzers that model it reject such a literal;
 * others fall back to plain `string` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-decimal-int-string` resolves to string&AccessoryDecimalIntegerStringType(inverse: true)
 * - PHPStan AccessoryDecimalIntegerStringType: covers "+1", "00", "18E+3", "1.2", "1,3", "foo"
 */

/**
 * @return non-decimal-int-string
 */
function returnsNonDecimalIntString() // T: non-decimal-int-string
{
    return '00';
}

function acceptsString(string $value): void
{
}

/**
 * @param non-decimal-int-string $value
 */
function acceptsNonDecimalIntString($value): void // T: non-decimal-int-string
{
}

// A `non-decimal-int-string` value always satisfies a native `string` parameter.
acceptsString(returnsNonDecimalIntString());

// Strings that keep their identity as an array key satisfy the parameter,
// whether or not they look numeric.
acceptsNonDecimalIntString('00');
acceptsNonDecimalIntString('1.2');
acceptsNonDecimalIntString('foo');

// Canonical decimal notation is the one thing excluded: enforcing analyzers
// reject it, others fall back to `string` and accept it.
acceptsNonDecimalIntString('123'); // E?: '123' is cast to int as an array key, so it is a decimal-int-string
acceptsNonDecimalIntString('-1'); // E?: '-1' is cast to int as an array key, so it is a decimal-int-string
