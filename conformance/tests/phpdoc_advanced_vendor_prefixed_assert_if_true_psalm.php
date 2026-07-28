<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertIfTruePsalm;

/**
 * Cross-tool handling of `@psalm-assert-if-true`
 *
 * The conditional assertion under the other vendor's prefix. Paired with the
 * `@phpstan-` file, it separates "does this tool support conditional
 * assertions" from "does this tool read this vendor's spelling of them".
 *
 * References:
 * - Psalm adding_assertions.md: @psalm-assert-if-true
 * - PHPStan phpdocs-basics: prefixed assertion tags
 */

/**
 * @psalm-assert-if-true int $value
 */
function isIntValue(mixed $value): bool // T: @psalm-assert-if-true
{
    return \is_int($value);
}

function example(mixed $value): void
{
    if (isIntValue($value)) {
        // Inside this branch the conditional assertion makes $value an int.
        echo \is_string($value) ? 'string' : 'int'; // E?: in the true branch, is_string() is always false
    }
}
