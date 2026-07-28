<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAssertIfTruePhpStan;

/**
 * Cross-tool handling of `@phpstan-assert-if-true`
 *
 * A conditional assertion: the guarantee holds only in the branch where the
 * function returned `true`. It asks more of a tool than a plain assertion,
 * because the narrowing has to be attached to a branch rather than to the rest
 * of the scope, and it is how a hand-written type predicate earns its keep.
 *
 * References:
 * - PHPStan phpdocs-basics: @phpstan-assert-if-true
 * - Psalm adding_assertions.md: assert-if-true
 */

/**
 * @phpstan-assert-if-true int $value
 */
function isIntValue(mixed $value): bool // T: @phpstan-assert-if-true
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
