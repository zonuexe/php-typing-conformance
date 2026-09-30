<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsIsCallableArrayNarrowing;

/**
 * `is_callable()` must refine an `array` arm of `array|callable` to something
 * that can actually be invoked.
 *
 * Mago 1.49 left the array arm as `array<array-key, mixed>` after the guard.
 * Three ways of consuming the narrowed value then disagreed, three lines apart:
 * a direct call, a first-class callable, and `Closure::fromCallable()`. The
 * unguarded call is the control. PHPStan rejects it; Psalm and mago stay
 * silent on invoking `array|callable` even without the guard, so it is
 * optional.
 *
 * Source: carthage-software/mago#2385 (fixed in 1.50.0).
 *
 * References:
 * - PHP `is_callable()`
 * - https://github.com/carthage-software/mago/issues/2385
 */

function takesClosure(\Closure $callback): void
{
    $callback();
}

/**
 * @param array<mixed>|callable $value
 */
function afterGuard(array|callable $value): void
{
    if (\is_callable($value)) {
        $value(); // Q: the array arm is callable after the guard
        takesClosure($value(...)); // Q?: first-class callable of the narrowed value
        takesClosure(\Closure::fromCallable($value)); // Q?: fromCallable of the narrowed value
    }
}

/**
 * @param array<mixed>|callable $value
 */
function beforeGuard(array|callable $value): void
{
    $value(); // E?: the array arm is not callable without the guard — Psalm and mago stay silent here
}
