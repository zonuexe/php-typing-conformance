<?php

declare(strict_types=1);

namespace Conformance\Tests\NativeTypesSignedIntInference;

/**
 * Arithmetic with known signs infers `positive-int` / `negative-int`.
 *
 * Native `int` parameters would accept every result, so the receivers are
 * the PHPDoc refinements. `10 + 5` satisfies `positive-int`; `10 + (-15)`
 * does not. The spellings themselves are `phpdoc_advanced_fallback_positive_int`
 * and `phpdoc_advanced_fallback_negative_int`.
 *
 * Source: phan/phan#5577 (6.0.8).
 *
 * References:
 * - https://github.com/phan/phan/pull/5577
 */

/**
 * @param positive-int $value
 */
function takesPositiveInt($value): void // T: positive-int
{
}

/**
 * @param negative-int $value
 */
function takesNegativeInt($value): void // T: negative-int
{
}

takesPositiveInt(10 + 5); // V
takesPositiveInt(10 + (-15)); // E?: 10 + (-15) is not positive-int
takesNegativeInt((-10) + (-5)); // V
takesNegativeInt(10 + 5); // E?: 10 + 5 is not negative-int
