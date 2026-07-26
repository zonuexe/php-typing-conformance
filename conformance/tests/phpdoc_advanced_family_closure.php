<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyClosure;

/**
 * `pure-closure` / `static-closure` refinements
 *
 * `pure-closure`, `static-closure` and `static-pure-closure` refine `Closure`
 * with purity/staticness. Analyzers that model them still accept any Closure at
 * the type level; a non-Closure argument is rejected. This mainly records which
 * analyzers recognize the keywords at all.
 *
 * References:
 * - PHPStan TypeNodeResolver pure-closure / static-closure / static-pure-closure
 */

/** @param pure-closure $fn */
function acceptsPureClosure($fn): void // T: pure-closure
{
}

/** @param static-closure $fn */
function acceptsStaticClosure($fn): void // T: static-closure
{
}

acceptsPureClosure(static fn (): int => 1);
acceptsPureClosure('strlen'); // E?: a callable-string is not a Closure

acceptsStaticClosure(static fn (): int => 1);
acceptsStaticClosure('strlen'); // E?: a callable-string is not a Closure
