<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhanReadOnlyWriteOnly;

/**
 * Cross-tool handling of `@phan-read-only` and `@phan-write-only`.
 *
 * Phan-specific property access polarity: read-only forbids external writes;
 * write-only forbids reads.
 *
 * References:
 * - Phan Annotating-Your-Source-Code: `@phan-read-only` and `@phan-write-only`
 */

final class Bag
{
    /** @phan-read-only */
    public int $id = 1; // T: @phan-read-only

    /** @phan-write-only */
    public int $scratch = 0; // T: @phan-write-only
}

$bag = new Bag();
echo $bag->id; // V
$bag->id = 2; // E?: @phan-read-only property written

$bag->scratch = 3; // V
echo $bag->scratch; // E?: @phan-write-only property read
