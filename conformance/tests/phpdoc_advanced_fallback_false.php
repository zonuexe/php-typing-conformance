<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackFalse;

/**
 * `false`
 *
 * The other constant boolean type, and the one PHP has always had a use for:
 * it is the failure half of every `string|false` the standard library returns.
 * Native since 8.2, like `true`. The probes are the opposite literal and a bool
 * of unknown value.
 *
 * References:
 * - PHPStan TypeNodeResolver `false` resolves to a constant boolean type
 */

/**
 * @return false
 */
function returnsFalse() // T: false
{
    return false;
}

function acceptsBool(bool $value): void
{
}

/**
 * @param false $value
 */
function acceptsFalse($value): void // T: false
{
}

// A `false` value always satisfies a native `bool` parameter.
acceptsBool(returnsFalse());

// The literal satisfies the parameter.
acceptsFalse(false);

// The opposite literal does not.
acceptsFalse(true); // E?: true is not the literal type false

function forwardsRuntimeBool(bool $value): void
{
    // Neither does a bool whose value is not known.
    acceptsFalse($value); // E?: bool is wider than the literal type false
}
