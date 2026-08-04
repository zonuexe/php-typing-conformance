<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertPhan;

/**
 * Cross-tool handling of `@phan-assert`.
 *
 * Phan's unconditional assertion tag, parallel to `@psalm-assert` /
 * `@phpstan-assert`. After the call, supporting tools treat the argument as
 * the asserted type.
 *
 * References:
 * - Phan Annotating-Your-Source-Code-V6.md: `@phan-assert`
 * - Psalm adding_assertions.md / PHPStan narrowing-types.md
 */

/**
 * @phan-assert int $value
 */
function assertIsInt(mixed $value): void // T: @phan-assert
{
    if (!\is_int($value)) {
        throw new \InvalidArgumentException('not an int');
    }
}

function takesString(string $value): void
{
}

function example(mixed $value): void
{
    assertIsInt($value);

    // Applying the assertion makes $value an int, so a string parameter must
    // reject it. Ignoring the tag leaves `mixed`, which often still accepts.
    takesString($value); // E?: after @phan-assert int, value is not string
}
