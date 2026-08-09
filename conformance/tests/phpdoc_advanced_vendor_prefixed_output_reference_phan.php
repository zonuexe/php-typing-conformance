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

    // NOTE: no analyzer in this suite discriminates on @phan-output-reference
    // here. Tools that ignore it fall back to the declared `?string` by-ref type
    // and report the null argument; Phan (the origin) does not surface this
    // by-ref flow at all, so it stays silent whether or not the tag is honoured.
    // The diagnostic therefore tracks the declared by-ref type, not the tag, and
    // must not be scored as enforcement.
    takesString($value); // E?[noise]: declared ?string by-ref type, independent of the tag
}
