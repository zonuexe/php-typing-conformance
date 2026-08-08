<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhpstanAssertSuperType;

/**
 * Cross-tool handling of `PHPStan\Testing\assertSuperType()`.
 *
 * Asserts that the actual type is a subtype of the expected super-type string.
 * A too-narrow super-type fails the assertion. A matching super-type is
 * silent (success), so only the mismatch line is an enforcement probe.
 *
 * References:
 * - PHPStan Testing helpers: `PHPStan\Testing\assertSuperType`
 *
 * @conformance-kind debug
 */

function example(int $value): void // T: PHPStan\Testing\assertSuperType
{
    // int is a subtype of int|string — silence when honoured (not a probe).
    \PHPStan\Testing\assertSuperType('int|string', $value); // E?[noise]

    // string is not a super-type of int — the sole enforcement probe.
    \PHPStan\Testing\assertSuperType('string', $value); // E?: Expected subtype of string, actual: int
}
