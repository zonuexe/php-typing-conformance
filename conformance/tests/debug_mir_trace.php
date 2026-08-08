<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugMirTrace;

/**
 * Cross-tool handling of mir's `@trace` diagnostic (MIR0221).
 *
 * Same spelling as the bare `@trace` / Psalm-compatible form. Mir emits an
 * info-level Trace issue with the inferred type; the suite enables
 * `--show-info` only for `debug_*` files so other tests stay quiet.
 *
 * Do not put trailing line comments on the same line as the @trace docblock,
 * and keep the next statement immediately after it (or only blank lines):
 * otherwise mir drops the annotation.
 *
 * References:
 * - mir site: Trace / MIR0221
 * - mir CHANGELOG: `@trace` docblock annotation
 *
 * @conformance-kind debug
 */

function example(int|string $value): void
{
    if (\is_int($value)) {
        /** @trace $value */
        echo $value; // T: @trace // E?: reports the inferred type (int)
    }
}
