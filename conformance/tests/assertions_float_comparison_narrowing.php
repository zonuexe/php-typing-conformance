<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsFloatComparisonNarrowing;

/**
 * Comparison narrowing of `float` (`>`, `>=`, `<`, `<=`).
 *
 * Integers are discrete, so `$n > 5` on `int` can become `int<6, max>`.
 * Floats are not: `$n > 1.5` excludes `1.5` without a user-visible next
 * value, while `$n >= 1.5` still admits it. Honour is silence on
 * `$n === 1.5` after `>=` / `<=`, and an always-false report after `>` / `<`.
 * The unguarded `=== 1.5` is the control: an unrestricted `float` may be
 * `1.5`.
 *
 * Source: phpstan/phpstan 2.3.6 (phpstan/phpstan-src#6937).
 *
 * References:
 * - https://phpstan.org/writing-php-code/phpdoc-types#float-ranges
 * - phpdoc_advanced_fallback_float_range for the `float<min, max>` spelling
 */

function afterGreaterThan(float $n): void
{
    if ($n > 1.5) {
        echo $n === 1.5 ? 'eq' : 'gt'; // E?: after > 1.5, === 1.5 is always false
    }
}

function afterGreaterEqual(float $n): void
{
    if ($n >= 1.5) {
        echo $n === 1.5 ? 'eq' : 'ge'; // Q: >= 1.5 still allows 1.5
    }
}

function afterLessThan(float $n): void
{
    if ($n < 1.5) {
        echo $n === 1.5 ? 'eq' : 'lt'; // E?: after < 1.5, === 1.5 is always false
    }
}

function afterLessEqual(float $n): void
{
    if ($n <= 1.5) {
        echo $n === 1.5 ? 'eq' : 'le'; // Q: <= 1.5 still allows 1.5
    }
}

function noGuard(float $n): void
{
    echo $n === 1.5 ? 'eq' : 'other'; // V: unrestricted float may be 1.5
}
