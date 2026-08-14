<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackPureClosure;

/**
 * `pure-closure`
 *
 * A `Closure` with no side effects — `pure-callable` narrowed to the class. The
 * two constraints fail independently: a callable string fails the `Closure`
 * half without any purity analysis, while an echoing closure fails only the
 * half that requires looking inside the body.
 *
 * An analyzer that never infers purity for a closure literal cannot construct a
 * value of this type at all, so it reports the valid probe and the `@return`
 * body as well. That is not an expectation of the test — it shows up under
 * false positives, which is the honest place for "rejects everything".
 *
 * References:
 * - PHPStan TypeNodeResolver `pure-closure` resolves to ClosureType::createPure()
 */

/**
 * @return pure-closure
 */
function returnsPureClosure() // T: pure-closure
{
    return static fn (int $value): int => $value + 1; // V
}

function acceptsClosure(\Closure $value): void
{
}

/**
 * @param pure-closure $value
 */
function acceptsPureClosure($value): void // T: pure-closure
{
}

// A `pure-closure` value always satisfies a native `Closure` parameter.
acceptsClosure(returnsPureClosure()); // V

// A closure that only computes satisfies the parameter.
acceptsPureClosure(static fn (int $value): int => $value + 1); // V

// A callable string is callable, but it is not a Closure.
acceptsPureClosure('strlen'); // E?: a callable-string is not a Closure

// A Closure that echoes fails the other half.
$impureClosure = static function (): void {
    echo 'side effect';
};
acceptsPureClosure($impureClosure); // E?: a closure with side effects is not a pure-closure
