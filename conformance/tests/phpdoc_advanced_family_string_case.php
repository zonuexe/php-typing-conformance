<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyStringCase;

/**
 * String case refinements (`lowercase-string`, `uppercase-string`, …)
 *
 * `lowercase-string`, `uppercase-string` and their non-empty variants are
 * case-constrained refinements of `string`. Analyzers that model them reject a
 * literal of the wrong case; others fall back to plain `string` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver lowercase-string / uppercase-string / non-empty-lowercase-string / non-empty-uppercase-string
 */

/** @param lowercase-string $value */
function acceptsLowercase($value): void
{
}

/** @param uppercase-string $value */
function acceptsUppercase($value): void
{
}

/** @param non-empty-lowercase-string $value */
function acceptsNonEmptyLowercase($value): void
{
}

/** @param non-empty-uppercase-string $value */
function acceptsNonEmptyUppercase($value): void
{
}

acceptsLowercase('abc');
acceptsLowercase('ABC'); // E?: 'ABC' is not a lowercase-string

acceptsUppercase('ABC');
acceptsUppercase('abc'); // E?: 'abc' is not an uppercase-string

acceptsNonEmptyLowercase('abc');
acceptsNonEmptyLowercase(''); // E?: '' is not a non-empty-lowercase-string

acceptsNonEmptyUppercase('ABC');
acceptsNonEmptyUppercase(''); // E?: '' is not a non-empty-uppercase-string
