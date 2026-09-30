<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsTernaryExactNarrowing;

/**
 * `$b === false` on a ternary must not leak its arm-narrowing out of the `if`.
 *
 * `$b = !$a ? $n : 0` then `if ($b === false)` proves `$a` is false *inside*
 * that branch (the `: 0` arm is never false). After the `if`, `$a` is still
 * `bool`. PHPStan 2.2.15 compared the arms by truthiness in exact contexts, so
 * `$a` stayed `false` on the fall-through.
 *
 * Source: phpstan/phpstan#15307, phpstan/phpstan-src#6580 (2.2.16).
 *
 * References:
 * - https://github.com/phpstan/phpstan/issues/15307
 * - https://github.com/phpstan/phpstan-src/pull/6580
 */

function takesBool(bool $value): void
{
}

function takesFalse(false $value): void
{
}

function afterExactCheck(bool $a, int|false $n): void
{
    $b = !$a ? $n : 0;

    if ($b === false) {
        takesFalse($a); // Q?: inside the branch, the `: 0` arm is impossible, so $a is false
    }

    takesFalse($a); // E: after the if, $a is still bool
    takesBool($a); // V
}
