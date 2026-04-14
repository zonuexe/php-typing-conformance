<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintCallableCompatible;

/**
 * Callable PHPDoc signatures should be compatible with callable typehints.
 *
 * References:
 * - NoVerify funcParamTypeMissMatch patch series (April 2025)
 */

/**
 * @param callable(int, string): string $callback
 */
function runCallback(callable $callback): void
{
    $callback(1, 'ok');
}
