<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertFalseConditionPhan;

/**
 * Cross-tool handling of `@phan-assert-false-condition`.
 *
 * Phan's spelling of "narrows when the call returns false", parallel to
 * `@psalm-assert-if-false` / `@phpstan-assert-if-false`.
 *
 * References:
 * - Phan Annotating-Your-Source-Code-V6.md: `@phan-assert-false-condition`
 * - Psalm / PHPStan assert-if-false
 */

/**
 * @phan-assert-false-condition int $value
 */
function isNotIntValue(mixed $value): bool // T: @phan-assert-false-condition
{
    return !\is_int($value);
}

function example(mixed $value): void
{
    if (isNotIntValue($value)) {
        // True branch: no assertion applied.
    } else {
        // False branch: the assertion makes $value an int.
        echo \is_string($value) ? 'string' : 'int'; // E?: in the false branch, is_string() is always false
    }
}
