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
    // Native type is int even when PHPDoc says positive-int — silence on match.
    \PHPStan\Testing\assertNativeType('int', $value); // E?[noise]

    // positive-int is not the native type string — the sole enforcement probe.
    \PHPStan\Testing\assertNativeType('positive-int', $value); // E?: Expected native type positive-int, actual: int
}
