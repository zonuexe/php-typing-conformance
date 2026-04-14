<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintArrayNullableMismatch;

/**
 * PHPDoc/native array mismatch for nullable array parameters.
 *
 * References:
 * - NoVerify funcParamTypeMissMatch patch series (April 2025)
 */

/**
 * @param list<int> $items // E[array_param_mismatch]: nullable array typehint is broader than the PHPDoc contract
 */
function takesItems(
    ?array $items, // E[array_param_mismatch]: nullable array typehint is broader than the PHPDoc contract
): void {
}
