<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPsalmTrace;

/**
 * Cross-tool handling of `@psalm-trace`.
 *
 * Psalm's type-inspection tag (applies to the next statement). Mago accepts
 * it as a compatibility alias. mir documents bare `@trace` only (see
 * `debug_mir_trace`) and does not treat `@psalm-trace` as equivalent here.
 *
 * Critical layout: keep the `@psalm-trace` docblock line free of trailing
 * `//` comments (mir drops bare `@trace` with the same pitfall; keep the
 * same hygiene here). Attribution: Mago may blame the enclosing `if`,
 * Psalm/pzoom the next statement — one optional probe group covers both.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-trace`
 * - Mago: recognizes `@psalm-trace` and suggests `Mago\inspect()`
 *
 * @conformance-kind debug
 */

function example(int|string $value): void // T: @psalm-trace
{
    if (\is_int($value)) { // E?[trace]: Mago may attribute the enclosing statement
        /** @psalm-trace $value */
        echo $value; // E?[trace]: Psalm / pzoom attribute the next statement
    }
}
