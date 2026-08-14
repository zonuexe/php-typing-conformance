<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedPurePhpStan;

/**
 * Cross-tool handling of `@phpstan-pure`
 *
 * The tag claims a function has no side effects, and unlike the other tags here
 * it is checked against the *body* rather than used at a call site: honouring it
 * means reporting the `echo` that contradicts the claim. That makes it the one
 * prefixed tag whose probe is inside the annotated declaration.
 *
 * References:
 * - PHPStan phpdocs-basics: @phpstan-pure / @phpstan-impure
 * - Psalm supported_annotations.md: @psalm-pure
 */

/**
 * @phpstan-pure
 */
function claimsPure(int $value): int // T: @phpstan-pure
{
    // The claim and the body disagree: honouring the tag means saying so.
    echo 'side effect'; // E?: an echo contradicts @phpstan-pure

    return $value + 1; // V
}

echo claimsPure(1);
