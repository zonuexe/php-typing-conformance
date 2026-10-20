<?php

declare(strict_types=1);

namespace Conformance\Tests\NativeTypesOrdRange;

/**
 * `ord()` returns `int<0, 255>`.
 *
 * Passing `ord('A')` to `int<0, 255>` is valid only when the call is typed
 * as the range rather than as plain `int` (`int` is not a subtype of
 * `int<0, 255>`). Passing it to a disjoint range is the rejection probe.
 *
 * Source: vimeo/psalm#10676 (5.22.0).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/10676
 * - phpdoc_advanced_fallback_int_range for the spelling itself
 */

/**
 * @param int<0, 255> $value
 */
function takesByte($value): void // T: int<0, 255>
{
}

/**
 * @param int<256, max> $value
 */
function takesAboveByte($value): void // T: int<256, max>
{
}

takesByte(ord('A')); // V: ord() is int<0, 255>
takesAboveByte(ord('A')); // E?: 65 is not in int<256, max>
