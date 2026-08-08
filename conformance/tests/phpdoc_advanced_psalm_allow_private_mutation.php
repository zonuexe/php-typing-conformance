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
        // Allowed under @psalm-allow-private-mutation; other tools may still
        // treat @readonly as fully frozen.
        $this->value++; // E?: tools that ignore allow-private-mutation treat @readonly strictly
    }
}

$counter = new Counter();
$counter->increment();
$counter->value = 99; // E?: readonly property assigned outside declaring class
