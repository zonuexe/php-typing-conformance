<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedReturnPhpStan;

/**
 * Cross-tool handling of `@phpstan-return`
 *
 * The prefixed form of `@return`, and the one a codebase reaches for when the
 * precise type would break a tool that reads the unprefixed tag. There is no
 * `@return` here to fall back on, so a tool that ignores the prefix has no
 * declared return type at all.
 *
 * References:
 * - PHPStan phpdocs-basics: prefixed @phpstan-return
 * - Psalm supported_annotations.md: @psalm-return
 */

/**
 * @phpstan-return int
 */
function returnsInt() // T: @phpstan-return
{
    return 1;
}

function takesString(string $value): void
{
}

// The prefixed tag says the return is an int, which is not a string.
takesString(returnsInt()); // E?: @phpstan-return int should be enforced at the call site
