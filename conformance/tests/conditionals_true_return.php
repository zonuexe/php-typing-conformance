<?php

declare(strict_types=1);

namespace Conformance\Tests\ConditionalsTrueReturn;

/**
 * Conditional return type driven by a boolean parameter (`is true`).
 *
 * The PHPStan 1.6 form: a flag chooses between the two sides of the
 * native union. Tools that expand the condition accept the matching
 * branch and reject the other; tools that fall back to `string|float` // E<noverify>: NoVerify reads $asFloat in the condition as a class name
 * reject both uses as a union, including the valid control.
 *
 * References:
 * - PHPStan 1.6.0: Conditional return types (`microtime` example)
 * - PHPStan phpdoc-types: Conditional return types
 * - Intelephense Type-System.md: Conditional Return Type
 */

/**
 * @return ($asFloat is true ? float : string)
 */
function asTime(bool $asFloat): string|float // T: ($asFloat is true ? float : string)
{
    return $asFloat ? 0.0 : '0';
}

function takesString(string $value): void
{
}

function takesFloat(float $value): void
{
}

takesString(asTime(false)); // V: false branch is string
takesFloat(asTime(true)); // V: true branch is float

takesString(asTime(true)); // E?: true branch is float, not string
takesFloat(asTime(false)); // E?: false branch is string, not float
