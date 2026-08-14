<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackKeyOfConstant;

/**
 * `key-of<T>` with a class-constant operand
 *
 * The plain `key-of` test writes its shape out inline; here the operand is a
 * reference to a class constant holding an array. Evaluating it needs one step
 * more than the inline form: the constant has to be resolved to its value and
 * the value to its keys before there is anything to take the keys *of*. A tool
 * can support `key-of<array{...}>` and still not follow the reference.
 *
 * References:
 * - PHPStan phpdoc-types: key-of with constant operands
 * - Psalm utility_types.md: key-of on class constants
 */

final class Config
{
    public const MAP = ['debug' => false, 'verbose' => true];
}

/**
 * @return key-of<Config::MAP>
 */
function returnsConfigKey() // T: key-of<Config::MAP>
{
    return \random_int(0, 1) === 1 ? 'debug' : 'verbose'; // V
}

function acceptsString(string $value): void
{
}

/**
 * @param key-of<Config::MAP> $key
 */
function acceptsConfigKey($key): void // T: key-of<Config::MAP>
{
}

// Every key of the constant is a string.
acceptsString(returnsConfigKey()); // V

// Both keys of the constant satisfy the parameter.
acceptsConfigKey('debug'); // V
acceptsConfigKey('verbose'); // V

// A string that is not one of them does not.
acceptsConfigKey('missing'); // E?: 'missing' is not a key of Config::MAP
