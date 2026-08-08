<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmExternalMutationFree;

/**
 * Cross-tool handling of `@psalm-external-mutation-free`.
 *
 * `$this` may still change; mutating *other* objects (parameters, globals) is
 * forbidden.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-external-mutation-free`
 */

final class Cell
{
    public string $value = '';

    /**
     * @psalm-external-mutation-free
     */
    public function copyFrom(Cell $other): void // T: @psalm-external-mutation-free
    {
        // Own state is allowed under external-mutation-free.
        $this->value = $other->value;

        // Mutating a parameter is not.
        $other->value = 'stolen'; // E?: external object write contradicts @psalm-external-mutation-free
    }
}
