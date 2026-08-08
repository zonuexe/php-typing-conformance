<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhpstanAssertNativeType;

/**
 * Cross-tool handling of `PHPStan\Testing\assertNativeType()`.
 *
 * Asserts the *native* type (typehints / inference without PHPDoc
 * refinement). Used in PHPStan's own fixtures alongside `assertType`.
 *
 * References:
 * - PHPStan Testing helpers: `PHPStan\Testing\assertNativeType`
 *
 * @conformance-kind debug
 */

/**
 * @param positive-int $value
 */
function example(int $value): void // T: PHPStan\Testing\assertNativeType
{
    // Native type is int even when PHPDoc says positive-int.
    \PHPStan\Testing\assertNativeType('int', $value); // E?: foreign undefined-function is incidental, not enforcement

    // positive-int is not the native type string.
    \PHPStan\Testing\assertNativeType('positive-int', $value); // E?: native type is int, not positive-int
}
