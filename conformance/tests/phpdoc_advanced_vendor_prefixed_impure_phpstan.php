<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedImpurePhpStan;

/**
 * Cross-tool handling of `@phpstan-impure`.
 *
 * PHPStan remembers return values of non-void functions by default. The tag
 * opts a function out of that memory so a second call can still be nullable
 * after a successful null check on the first call.
 *
 * References:
 * - PHPStan phpdocs-basics: Impure functions
 * - PHPStan blog: Remembering and forgetting returned values
 */

/**
 * @phpstan-impure
 */
function flip(): ?string // T: @phpstan-impure
{
    return \rand(0, 1) === 1 ? 'heads' : null;
}

function demo(): void
{
    if (flip() !== null) {
        // Under @phpstan-impure the second call is still ?string.
        echo \strlen(flip()); // E?: second flip() is still string|null under @phpstan-impure
    }
}
