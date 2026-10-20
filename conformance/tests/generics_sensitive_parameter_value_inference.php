<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsSensitiveParameterValueInference;

/**
 * `SensitiveParameterValue` is generic in its wrapped value.
 *
 * `new SensitiveParameterValue('secret')` is `SensitiveParameterValue<string>`,
 * so `getValue()` is `string`. Tools that leave the class untemplated type
 * `getValue()` as `mixed`.
 *
 * Source: phpstan/phpstan-src#6272 (2.2.10).
 *
 * References:
 * - https://github.com/phpstan/phpstan-src/pull/6272
 * - PHP `SensitiveParameterValue::getValue()`
 */

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

$wrapped = new \SensitiveParameterValue('secret');
takesString($wrapped->getValue()); // V: constructor argument pins T to string
takesInt($wrapped->getValue()); // E?: getValue() is string, not int

$intWrapped = new \SensitiveParameterValue(1);
takesInt($intWrapped->getValue()); // V
takesString($intWrapped->getValue()); // E?: getValue() is int, not string
