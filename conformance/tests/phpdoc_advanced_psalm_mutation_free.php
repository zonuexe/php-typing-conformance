<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmMutationFree;

/**
 * Cross-tool handling of `@psalm-mutation-free`.
 *
 * Method-level: the body must not mutate instance state. Constructors are
 * exempt; this probes a normal method.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-mutation-free`
 */

final class Counter
{
    public int $value = 0;

    /**
     * @psalm-mutation-free
     */
    public function bump(): void // T: @psalm-mutation-free
    {
        $this->value++; // E?: property write contradicts @psalm-mutation-free
    }
}
