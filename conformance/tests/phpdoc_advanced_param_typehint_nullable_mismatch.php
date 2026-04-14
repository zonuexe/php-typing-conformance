<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintNullableMismatch;

/**
 * PHPDoc/native type mismatch for nullable parameters.
 *
 * References:
 * - NoVerify funcParamTypeMissMatch patch series (April 2025)
 */

/**
 * @param string $name // E[param_mismatch]: nullable typehint is broader than the PHPDoc contract
 */
function greet(
    ?string $name, // E[param_mismatch]: nullable typehint is broader than the PHPDoc contract
): void {
}
