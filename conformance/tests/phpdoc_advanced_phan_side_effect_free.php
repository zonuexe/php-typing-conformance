<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhanSideEffectFree;

/**
 * Cross-tool handling of `@phan-side-effect-free`.
 *
 * Phan's purity-adjacent claim on a function. Honouring it means reporting
 * side effects in the body (similar spirit to `@psalm-pure` / `@phpstan-pure`).
 *
 * References:
 * - Phan Annotating-Your-Source-Code: `@phan-side-effect-free`
 */

/**
 * @phan-side-effect-free
 */
function claimsSideEffectFree(int $value): int // T: @phan-side-effect-free
{
    echo 'side effect'; // E?: echo contradicts @phan-side-effect-free

    return $value + 1; // V
}

echo claimsSideEffectFree(1);
