<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintByrefCompatible;

/**
 * By-reference parameters should match even if PHPDoc omits the reference marker.
 *
 * References:
 * - NoVerify funcParamTypeMissMatch patch series (April 2025)
 */

/**
 * @param list<int> $nodes
 * @param list<int> $tokens
 */
function clearStandaloneLines(array &$nodes, array &$tokens): void
{
}
