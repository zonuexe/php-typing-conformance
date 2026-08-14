<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonEmptyMixed;

/**
 * `non-empty-mixed`
 *
 * `mixed` with the falsy values subtracted — not "mixed but set". Since `mixed`
 * is the type an analyzer falls back to when it understands nothing, this is
 * the spelling where "recognized but not enforced" and "not recognized at all"
 * look most alike from the outside; only the probes tell them apart.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-empty-mixed` resolves to MixedType(true, StaticTypeFactory::falsey())
 */

/**
 * @return non-empty-mixed
 */
function returnsNonEmptyMixed() // T: non-empty-mixed
{
    return 1; // V
}

function acceptsMixed(mixed $value): void
{
}

/**
 * @param non-empty-mixed $value
 */
function acceptsNonEmptyMixed($value): void // T: non-empty-mixed
{
}

// A `non-empty-mixed` value always satisfies a native `mixed` parameter.
acceptsMixed(returnsNonEmptyMixed()); // V

// Any truthy value satisfies the parameter, of any type.
acceptsNonEmptyMixed(1); // V
acceptsNonEmptyMixed('x'); // V
acceptsNonEmptyMixed([1]); // V
acceptsNonEmptyMixed(new \stdClass()); // V

// The falsy values are subtracted, whatever their type.
acceptsNonEmptyMixed(''); // E?: '' is falsy, so it is not a non-empty-mixed
acceptsNonEmptyMixed(0); // E?: 0 is falsy, so it is not a non-empty-mixed
acceptsNonEmptyMixed([]); // E?: [] is falsy, so it is not a non-empty-mixed
acceptsNonEmptyMixed(null); // E?: null is falsy, so it is not a non-empty-mixed
