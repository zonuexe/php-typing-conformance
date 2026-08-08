<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhpstanAssertType;

/**
 * Cross-tool handling of `PHPStan\Testing\assertType()`.
 *
 * Fixture-style assertion: the first argument is the expected type string.
 * When the actual type disagrees, PHPStan reports a type assertion failure.
 * Correct assertions are silent — that silence is success, not a miss, so
 * only the *mismatch* line is an enforcement probe.
 *
 * References:
 * - PHPStan Testing helpers: `PHPStan\Testing\assertType`
 *
 * @conformance-kind debug
 */

function example(int $value): void // T: PHPStan\Testing\assertType
{
    // Correct: silence when honoured. Foreign undefined-function is noise only.
    \PHPStan\Testing\assertType('int', $value); // E?[noise]

    // Wrong expected type: the sole enforcement probe.
    \PHPStan\Testing\assertType('string', $value); // E?: Expected type string, actual: int
}
