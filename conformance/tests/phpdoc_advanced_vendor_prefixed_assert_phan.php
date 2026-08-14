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

function takesInt(int $value): void
{
}

function example(mixed $value): void
{
    assertIsInt($value);

    takesInt($value); // V

    // Applying the assertion makes $value an int, so an is_string() branch is
    // provably dead. A tool that ignores the tag keeps `mixed`, where
    // is_string() is a live check and nothing is reported — so the diagnostic
    // is emitted only by a tool that actually honoured @phan-assert.
    echo \is_string($value) ? 'string' : 'int'; // E?: after @phan-assert int, is_string() is always false
}
