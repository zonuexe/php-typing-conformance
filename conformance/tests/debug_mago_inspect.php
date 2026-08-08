<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugMagoInspect;

/**
 * Cross-tool handling of `Mago\inspect()`.
 *
 * Mago's type-inspection helper (any expression, not only variables). Other
 * analyzers typically treat it as an undefined function.
 *
 * References:
 * - Mago analyzer: `Mago\inspect()` type-inspection help diagnostic
 *
 * @conformance-kind debug
 */

function example(int|string $value): void // T: Mago\inspect
{
    if (\is_int($value)) {
        \Mago\inspect($value); // E?: reports the inferred type; foreign undefined-function is not enforcement
    }
}
