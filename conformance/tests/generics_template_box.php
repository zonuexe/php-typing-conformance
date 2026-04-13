<?php

declare(strict_types=1);

/**
 * Basic PHPDoc template propagation checks.
 *
 * References:
 * - phpDocumentor generics-like annotations
 * - PHPStan template syntax
 * - Psalm template syntax
 */

/**
 * @template T
 */
final class Box
{
    /**
     * @param T $value
     */
    public function __construct(
        public mixed $value,
    ) {
    }
}

/**
 * @param Box<int> $box
 */
function takesIntBox(Box $box): void
{
}

takesIntBox(new Box(1));
takesIntBox(new Box('x')); // E: Box<string> is not accepted where Box<int> is required
