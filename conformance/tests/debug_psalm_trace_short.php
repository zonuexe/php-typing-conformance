<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPsalmTraceShort;

/**
 * Cross-tool handling of bare `@trace` (short form of `@psalm-trace`).
 *
 * Mago and mir document accepting bare @trace. Psalm may require the psalm-
 * prefix.
 *
 * References:
 * - mir CHANGELOG / Trace (MIR0221): bare @trace annotation
 * - Mago psalm-trace compatibility notes
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
