<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackPureCallable;

/**
 * `pure-callable`
 *
 * A callable with no side effects. Unlike the other callable refinements this
 * one cannot be checked from a signature: deciding it means looking inside the
 * body for writes, echoes and calls to impure functions. Analyzers that only
 * parse the keyword accept any callable here.
 *
 * References:
 * - PHPStan TypeNodeResolver `pure-callable` resolves to CallableType(isPure: yes)
 */

function impureFunction(): int
{
    echo 'side effect';

    return 1;
}

/**
 * @return pure-callable
 */
function returnsPureCallable() // T: pure-callable
{
    return static fn (int $value): int => $value + 1; // V
}

function acceptsCallable(callable $value): void
{
}

/**
 * @param pure-callable $value
 */
function acceptsPureCallable($value): void // T: pure-callable
{
}

// A `pure-callable` value always satisfies a native `callable` parameter.
acceptsCallable(returnsPureCallable()); // V

// A closure that only computes satisfies the parameter.
acceptsPureCallable(static fn (int $value): int => $value + 1); // V

// A closure that echoes does not.
$impureClosure = static function (): void {
    echo 'side effect';
};
acceptsPureCallable($impureClosure); // E?: a closure with side effects is not a pure-callable

// Neither does a named function whose body has side effects.
acceptsPureCallable(impureFunction(...)); // E?: an impure function is not a pure-callable

// A value that is not callable at all fails the base type too.
acceptsPureCallable(1); // E?: 1 is not callable
