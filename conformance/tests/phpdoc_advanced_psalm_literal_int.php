<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmLiteralInt;

/**
 * `literal-int` is a Psalm-only literal-ness marker.
 *
 * `literal-int` matches an integer known at analysis time (a literal or a value
 * narrowed to one), not an arbitrary runtime `int`. Psalm models the
 * distinction; other analyzers do not recognize the keyword and fall back to
 * plain `int`.
 *
 * References:
 * - Psalm TNonspecificLiteralInt (`literal-int`)
 */

/**
 * @param literal-int $value
 */
function acceptsLiteralInt($value): void // T: literal-int
{
}

function forwardsRuntimeInt(int $value): void
{
    // A general runtime int is not known to be literal.
    acceptsLiteralInt($value); // E?: an arbitrary int is not a literal-int
}

// A literal integer satisfies the parameter.
acceptsLiteralInt(42); // V
