<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPsalmTrace;

/**
 * Cross-tool handling of `@psalm-trace`.
 *
 * Psalm's type-inspection tag (applies to the next statement). Mago accepts
 * it as a compatibility alias; mir accepts `@trace` / `@psalm-trace` at info
 * level when `--show-info` is on.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-trace`
 * - Mago: recognizes `@psalm-trace` and suggests `Mago\inspect()`
 *
 * @conformance-kind debug
 */

function example(int|string $value): void
{
    if (\is_int($value)) {
        /** @psalm-trace $value */
        echo $value; // T: @psalm-trace // E?: reports the inferred type (int)
    }
}
