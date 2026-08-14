<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackTrue;

/**
 * `true`
 *
 * The constant boolean type. PHP 8.2 made it a native type as well, so this is
 * one of the few spellings where the docblock and the signature can say the
 * same thing — which makes the docblock form easy to under-test. The probes are
 * the opposite literal and a bool of unknown value.
 *
 * References:
 * - PHPStan TypeNodeResolver `true` resolves to a constant boolean type
 */

/**
 * @return true
 */
function returnsTrue() // T: true
{
    return true; // V
}

function acceptsBool(bool $value): void
{
}

/**
 * @param true $value
 */
function acceptsTrue($value): void // T: true
{
}

// A `true` value always satisfies a native `bool` parameter.
acceptsBool(returnsTrue()); // V

// The literal satisfies the parameter.
acceptsTrue(true); // V

// The opposite literal does not.
acceptsTrue(false); // E?: false is not the literal type true

function forwardsRuntimeBool(bool $value): void
{
    // Neither does a bool whose value is not known.
    acceptsTrue($value); // E?: bool is wider than the literal type true
}
