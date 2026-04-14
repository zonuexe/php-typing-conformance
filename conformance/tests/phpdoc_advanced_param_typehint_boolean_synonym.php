<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintBooleanSynonym;

/**
 * boolean/bool should be treated as equivalent for compatibility checks.
 *
 * References:
 * - NoVerify funcParamTypeMissMatch patch series (April 2025)
 */ // E?: some tools may report a docblock spelling preference

/**
 * @param boolean $flag
 */
function takesFlag(bool $flag): void
{
}
