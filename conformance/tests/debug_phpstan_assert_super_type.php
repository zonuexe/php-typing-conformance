<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhpstanAssertSuperType;

/**
 * Cross-tool handling of `PHPStan\Testing\assertSuperType()`.
 *
 * Asserts that the actual type is a subtype of the expected super-type string.
 * A too-narrow super-type fails the assertion.
 *
 * References:
 * - PHPStan Testing helpers: `PHPStan\Testing\assertSuperType`
 *
 * @conformance-kind debug
 */

function example(int $value): void // T: PHPStan\Testing\assertSuperType
{
    // int is a subtype of int|string — silent when honoured.
    \PHPStan\Testing\assertSuperType('int|string', $value); // E?: tools that do not know assertSuperType report undefined function

    // string is not a super-type of int.
    \PHPStan\Testing\assertSuperType('string', $value); // E?: super-type assertion fails (or undefined function)
}
