<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertPsalm;

/**
 * Cross-tool handling of `@psalm-assert`
 *
 * The same guarantee as `@phpstan-assert` under the other vendor's prefix.
 * Since the two tags mean the same thing, a tool that honours one and not the
 * other is choosing which dialect to read rather than which feature to support
 * — which is the whole point of testing the prefixes separately.
 *
 * References:
 * - Psalm adding_assertions.md: @psalm-assert
 * - PHPStan phpdocs-basics: prefixed assertion tags
 */

/**
 * @psalm-assert int $value
 */
function assertIsInt(mixed $value): void // T: @psalm-assert
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

    // Applying the assertion makes $value an int, so this test can never be
    // true. Ignoring the tag leaves `mixed`, where it is an ordinary check.
    echo \is_string($value) ? 'string' : 'int'; // E?: after @psalm-assert int, is_string() is always false
}
