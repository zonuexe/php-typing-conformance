<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedReturnPsalm;

/**
 * Cross-tool handling of `@psalm-return`
 *
 * The prefixed return tag under the other vendor. Paired with the `@phpstan-`
 * file it separates "reads a prefixed return tag" from "reads this vendor's
 * prefix", which a single test carrying both tags could not.
 *
 * References:
 * - Psalm supported_annotations.md: @psalm-return
 * - PHPStan phpdocs-basics: prefixed @phpstan-return
 */

/**
 * @psalm-return int
 */
function returnsInt() // T: @psalm-return
{
    return 1; // V
}

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

takesInt(returnsInt()); // V

// The prefixed tag says the return is an int, which is not a string.
takesString(returnsInt()); // E?: @psalm-return int should be enforced at the call site
