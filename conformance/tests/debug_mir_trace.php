<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugMirTrace;

/**
 * Cross-tool handling of mir's bare `@trace` diagnostic (MIR0221).
 *
 * This is mir's native spelling (not a Psalm short form — Psalm only
 * documents `@psalm-trace`; see `debug_psalm_trace`). Mago accepts bare
 * `@trace` as a compatibility alias; pzoom (Psalm port) may as well.
 *
 * Mir emits an info-level Trace issue; the suite enables `--show-info` only
 * for `debug_*` files so other tests stay quiet.
 *
 * Critical layout: the `@trace` docblock line must have **no** trailing `//`
 * comments, or mir drops the annotation. Attribution varies — Mago often
 * blames the enclosing `if`, mir/pzoom the next statement — so both share
 * one optional probe group.
 *
 * References:
 * - mir site: Trace / MIR0221
 * - mir CHANGELOG: `@trace` docblock annotation
 *
 * @conformance-kind debug
 */

function example(int|string $value): void // T: @trace
{
    if (\is_int($value)) { // E?[trace]: Mago may attribute the enclosing statement
        /** @trace $value */
        echo $value; // E?[trace]: mir / pzoom attribute the next statement
    }
}
