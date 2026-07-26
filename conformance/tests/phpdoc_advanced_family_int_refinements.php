<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyIntRefinements;

/**
 * Integer sign refinements (`negative-int`, `non-zero-int`, …)
 *
 * `negative-int`, `non-positive-int`, `non-negative-int` and `non-zero-int` are
 * sign-bounded refinements of `int`. Analyzers that model them reject an
 * out-of-range literal; others fall back to plain `int` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver negative-int / non-positive-int / non-negative-int / non-zero-int
 */

/** @param negative-int $value */
function acceptsNegativeInt($value): void // T: negative-int
{
}

/** @param non-positive-int $value */
function acceptsNonPositiveInt($value): void // T: non-positive-int
{
}

/** @param non-negative-int $value */
function acceptsNonNegativeInt($value): void // T: non-negative-int
{
}

/** @param non-zero-int $value */
function acceptsNonZeroInt($value): void // T: non-zero-int
{
}

acceptsNegativeInt(-5);
acceptsNegativeInt(5); // E?: 5 is not a negative-int

acceptsNonPositiveInt(0);
acceptsNonPositiveInt(5); // E?: 5 is not a non-positive-int

acceptsNonNegativeInt(0);
acceptsNonNegativeInt(-5); // E?: -5 is not a non-negative-int

acceptsNonZeroInt(5);
acceptsNonZeroInt(0); // E?: 0 is not a non-zero-int
