<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackValueOfConstant;

/**
 * `value-of<T>` with a class-constant operand
 *
 * The mirror of the constant-operand `key-of` case: the operand names a class
 * constant, and the type is the union of that array's values, `1|10`. The
 * probe sits *between* the two members — `5` is an int like both of them, so
 * a tool that resolves the constant but widens the values to `int` accepts it,
 * and only a tool that keeps the literal union rejects it.
 *
 * References:
 * - PHPStan phpdoc-types: value-of with constant operands
 * - Psalm utility_types.md: value-of on class constants
 */

final class Levels
{
    public const MAP = ['low' => 1, 'high' => 10];
}

/**
 * @return value-of<Levels::MAP>
 */
function returnsLevel() // T: value-of<Levels::MAP>
{
    return \random_int(0, 1) === 1 ? 1 : 10;
}

function acceptsInt(int $value): void
{
}

/**
 * @param value-of<Levels::MAP> $value
 */
function acceptsLevel($value): void // T: value-of<Levels::MAP>
{
}

// Every value of the constant is an int.
acceptsInt(returnsLevel());

// Both values of the constant satisfy the parameter.
acceptsLevel(1);
acceptsLevel(10);

// An int between them does not: the type is the union of the literals, not
// their common base type.
acceptsLevel(5); // E?: 5 is not a value of Levels::MAP

// One of the constant's keys is not one of its values either.
acceptsLevel('low'); // E?: 'low' is a key of Levels::MAP, not a value of it
