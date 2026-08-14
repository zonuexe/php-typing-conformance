<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackStaticClosure;

/**
 * `static-closure`
 *
 * A `Closure` declared `static`, which is the one that cannot be bound to an
 * object and so can never touch `$this`. Unlike purity this is written in the
 * syntax, so an analyzer has no excuse for missing it — the probe is an
 * ordinary closure, identical but for the keyword.
 *
 * References:
 * - PHPStan TypeNodeResolver `static-closure` resolves to ClosureType(isStatic: yes)
 */

/**
 * @return static-closure
 */
function returnsStaticClosure() // T: static-closure
{
    return static fn (): int => 1; // V
}

function acceptsClosure(\Closure $value): void
{
}

/**
 * @param static-closure $value
 */
function acceptsStaticClosure($value): void // T: static-closure
{
}

// A `static-closure` value always satisfies a native `Closure` parameter.
acceptsClosure(returnsStaticClosure()); // V

// A static closure satisfies the parameter.
acceptsStaticClosure(static fn (): int => 1); // V

// A closure without the keyword does not.
acceptsStaticClosure(fn (): int => 1); // E?: a non-static closure is not a static-closure

// Nor does a callable that is not a Closure at all.
acceptsStaticClosure('strlen'); // E?: a callable-string is not a Closure
