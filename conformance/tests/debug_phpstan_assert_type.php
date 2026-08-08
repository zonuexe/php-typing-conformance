<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhpstanAssertType;

/**
 * Cross-tool handling of `PHPStan\Testing\assertType()`.
 *
 * Fixture-style assertion: the first argument is the expected type string.
 * When the actual type disagrees, PHPStan reports a type assertion failure.
 * Correct assertions are silent.
 *
 * References:
 * - PHPStan Testing helpers: `PHPStan\Testing\assertType`
 *
 * @conformance-kind debug
 */

function example(int $value): void // T: PHPStan\Testing\assertType
{
    // Correct: silent when the helper is honoured; undefined-function elsewhere (not enforcement).
    \PHPStan\Testing\assertType('int', $value); // E?: foreign undefined-function is incidental, not enforcement

    // Wrong expected type: diagnostic when assertType is live.
    \PHPStan\Testing\assertType('string', $value); // E?: Expected type string, actual: int
}
