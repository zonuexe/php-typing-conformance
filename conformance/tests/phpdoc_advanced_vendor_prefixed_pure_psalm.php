<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedPurePsalm;

/**
 * Cross-tool handling of `@psalm-pure`
 *
 * The purity claim under the other vendor's prefix, checked the same way: the
 * body contradicts the tag, and honouring it means reporting the contradiction.
 *
 * References:
 * - Psalm supported_annotations.md: @psalm-pure
 * - PHPStan phpdocs-basics: @phpstan-pure
 */

/**
 * @psalm-pure
 */
function claimsPure(int $value): int // T: @psalm-pure
{
    // The claim and the body disagree: honouring the tag means saying so.
    echo 'side effect'; // E?: an echo contradicts @psalm-pure

    return $value + 1; // V
}

echo claimsPure(1);
