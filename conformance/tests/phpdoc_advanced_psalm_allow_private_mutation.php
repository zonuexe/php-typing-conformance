<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmAllowPrivateMutation;

/**
 * Cross-tool handling of `@psalm-allow-private-mutation` with `@readonly`.
 *
 * Public readonly surface with private (same-class) writes still allowed.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-allow-private-mutation`
 */

final class Counter
{
    /**
     * @readonly
     * @psalm-allow-private-mutation
     */
    public int $value = 0; // T: @psalm-allow-private-mutation

    public function increment(): void
    {
        // Allowed under the tag (Psalm baseline). Quiet is origin-only;
        // foreign tools that treat plain @readonly as frozen are noise here.
        $this->value++; // Q?<psalm> // Q?<pzoom> // E?[noise]
    }
}

$counter = new Counter();
$counter->increment();
// External write must still fail under @readonly.
$counter->value = 99; // E?: readonly property assigned outside declaring class
