<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintBooleanSynonym;

/**
 * `boolean` PHPDoc synonym is compatible with native `bool`.
 *
 * `boolean` (PHPDoc) and native `bool` name the same type, so values should
 * flow freely across a boundary that spells it either way — the library
 * compatibility case where one side documents `@return boolean` and the other
 * declares a native `bool` parameter. Analyzers should treat the synonym as
 * compatible while still enforcing it as a real boolean type.
 *
 * References:
 * - NoVerify funcParamTypeMissMatch patch series (April 2025)
 */

/**
 * @return boolean
 */
function returnsBoolean() // T: boolean
{
    return true;
}

function acceptsOnlyBool(bool $flag): void
{
}

/**
 * @param boolean $flag
 */
function acceptsOnlyBoolean($flag): void // T: boolean
{
}

// A `boolean`-documented value should satisfy a native `bool` parameter.
acceptsOnlyBool(returnsBoolean());

// A native `bool` should satisfy a `boolean`-documented parameter.
acceptsOnlyBoolean(true);

// `boolean` must still be enforced as a real boolean, not silently ignored.
acceptsOnlyBoolean('not a bool'); // E?: string should be rejected where @param boolean is required
