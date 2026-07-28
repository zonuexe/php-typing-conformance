<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertPhpStan;

/**
 * Cross-tool handling of `@phpstan-assert`
 *
 * The tag says that a call to this function guarantees the type of an argument
 * from that point on. Unlike a `@param` tag it changes what the *caller* knows,
 * so ignoring it is silent: nothing is rejected, a narrowing simply does not
 * happen. The probe is a check that only becomes impossible once the assertion
 * has been applied.
 *
 * References:
 * - PHPStan phpdocs-basics: @phpstan-assert
 * - Psalm adding_assertions.md
 */

/**
 * @phpstan-assert int $value
 */
function assertIsInt(mixed $value): void // T: @phpstan-assert
{
    if (!\is_int($value)) {
        throw new \InvalidArgumentException('not an int');
    }
}

function example(mixed $value): void
{
    assertIsInt($value);

    // Applying the assertion makes $value an int, so this test can never be
    // true. Ignoring the tag leaves `mixed`, where it is an ordinary check.
    echo \is_string($value) ? 'string' : 'int'; // E?: after @phpstan-assert int, is_string() is always false
}
