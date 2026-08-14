<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsParamOut;

/**
 * By-reference output parameters via `@param-out`.
 *
 * After `assignString($value)`, analyzers that honor `@param-out string`
 * narrow `$value` to string so a later `takesString($value)` is valid.
 * Tools that ignore the annotation keep the pre-call type (`null`) and
 * report an argument error — or accept via mixed looseness.
 *
 * References:
 * - Psalm assertion / param-out annotations
 * - PHPStan param-out support
 */

/**
 * @param-out string $value
 * @psalm-param-out string $value
 * @phpstan-param-out string $value
 */
function assignString(?string &$value): void // T: @param-out
{
    $value = 'assigned';
}

function takesString(string $value): void
{
}

function example(): void
{
    $value = null;
    assignString($value);

    // After @param-out string, $value is string, so this must be silent.
    // A diagnostic here means the tag was ignored (or the tool never flags
    // null for string). Silence is the honour signal, not a report.
    takesString($value); // Q?: after @param-out string, $value is string
}
