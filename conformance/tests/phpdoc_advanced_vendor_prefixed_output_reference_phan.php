<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedOutputReferencePhan;

/**
 * Cross-tool handling of `@phan-output-reference`.
 *
 * Marks a by-ref parameter as an output: after the call, supporting tools treat
 * the argument as definitely assigned (and often non-null) even when the
 * pre-call type included null.
 *
 * References:
 * - Phan Annotating-Your-Source-Code: `@phan-output-reference`
 * - Parallel idea: `@param-out` (covered separately)
 */

/**
 * @param string|null $value @phan-output-reference
 */
function assignString(?string &$value): void // T: @phan-output-reference
{
    // Keep a null path so tools do not complain the by-ref type is over-wide.
    $value = \rand(0, 10) > 0 ? 'assigned' : null;
}

function takesString(string $value): void
{
}

function example(): void
{
    $value = null;
    assignString($value);

    // After output-reference, $value should be string for supporting tools.
    takesString($value); // E?: tools that ignore @phan-output-reference may still see null
}
