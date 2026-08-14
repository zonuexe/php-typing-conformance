<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertTrueConditionPhan;

/**
 * Cross-tool handling of `@phan-assert-true-condition`.
 *
 * Phan's name for the conditional "narrows when the call returns true"
 * assertion. Other tools spell the same idea as `@psalm-assert-if-true` /
 * `@phpstan-assert-if-true`.
 *
 * References:
 * - Phan Annotating-Your-Source-Code-V6.md: `@phan-assert-true-condition`
 * - Psalm / PHPStan assert-if-true
 */

/**
 * @phan-assert-true-condition int $value
 */
function isIntValue(mixed $value): bool // T: @phan-assert-true-condition
{
    return \is_int($value);
}

function takesInt(int $value): void
{
}

function example(mixed $value): void
{
    if (isIntValue($value)) {
        takesInt($value); // V

        // Inside this branch the conditional assertion makes $value an int.
        echo \is_string($value) ? 'string' : 'int'; // E?: in the true branch, is_string() is always false
    }
}
