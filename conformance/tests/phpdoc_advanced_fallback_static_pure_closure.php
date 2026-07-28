<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackStaticPureClosure;

/**
 * `static-pure-closure`
 *
 * Both closure constraints at once: declared `static` and free of side effects.
 * Each half is probed on its own, so a row that enforces one and not the other
 * says which of the two an analyzer actually models — the syntactic half is
 * much cheaper to check than the semantic one.
 *
 * An analyzer that never infers purity for a closure literal cannot construct a
 * value of this type at all, so it reports the valid probe and the `@return`
 * body as well. That is not an expectation of the test — it shows up under
 * false positives, which is the honest place for "rejects everything".
 *
 * References:
 * - PHPStan TypeNodeResolver `static-pure-closure` resolves to ClosureType(impurePoints: [], isStatic: yes)
 */

/**
 * @return static-pure-closure
 */
function returnsStaticPureClosure() // T: static-pure-closure
{
    return static fn (int $value): int => $value + 1;
}

function acceptsClosure(\Closure $value): void
{
}

/**
 * @param static-pure-closure $value
 */
function acceptsStaticPureClosure($value): void // T: static-pure-closure
{
}

// A `static-pure-closure` value always satisfies a native `Closure` parameter.
acceptsClosure(returnsStaticPureClosure());

// A static closure that only computes satisfies both halves.
acceptsStaticPureClosure(static fn (int $value): int => $value + 1);

// Pure, but not static.
acceptsStaticPureClosure(fn (int $value): int => $value + 1); // E?: a non-static closure is not a static-pure-closure

// Static, but not pure.
$impureClosure = static function (): void {
    echo 'side effect';
};
acceptsStaticPureClosure($impureClosure); // E?: a closure with side effects is not a static-pure-closure
