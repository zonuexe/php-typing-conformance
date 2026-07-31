<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackConstantOffsetTemplateKey;

/**
 * `CONSTANT[T]`
 *
 * An offset into a constant array where the key is a template parameter, so
 * the return type is decided by the argument the caller passed. Resolving it
 * takes three steps: the constant has to be reachable in type position, the
 * `key-of<>` bound has to admit the literal argument, and the offset has to be
 * taken per key rather than over the table as a whole.
 *
 * The two values are deliberately of different native types, so the probes
 * need nothing more exotic than `int` and `string` to state. A tool that
 * resolves the offset only as far as the union of the table's values fails the
 * calls that should pass, the same way one that ignores the docblock and keeps
 * the native `int|string` does. Both readings surface as complaints about the
 * three calls meant to pass rather than as silence.
 *
 * References:
 * - PHPStan phpdoc-types: offset access on constants
 * - phpdoc_advanced_fallback_key_of_constant for the bound on its own
 */

const ID_TABLE = [
    'immutable' => 1,
    'mutable' => 'two',
];

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

/**
 * @template T of key-of<ID_TABLE>
 * @param T $type
 * @return ID_TABLE[T]
 */
function lookUp(string $type = 'immutable'): int|string // T: ID_TABLE[T]
{
    return ID_TABLE[$type];
}

// Each key resolves to its own value, the default argument included.
takesInt(lookUp());
takesInt(lookUp('immutable'));
takesString(lookUp('mutable'));

// Reading the table as a whole instead of per key would let these through.
takesString(lookUp('immutable')); // E?: 'immutable' maps to int, not string
takesInt(lookUp('mutable')); // E?: 'mutable' maps to string, not int
