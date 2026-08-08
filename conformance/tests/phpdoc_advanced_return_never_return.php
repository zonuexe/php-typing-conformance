<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedReturnNeverReturn;

/**
 * `never-return` as a bottom-type spelling.
 *
 * PHPStan documents `never`, `never-return`, and `never-returns` as
 * equivalent. Sibling files cover `noreturn` and `never-returns`.
 *
 * References:
 * - PHPStan phpdoc-types: Bottom type
 *
 * Tools that do not know the alias may treat `never-return` as a class name
 * on the declaration line; that is expected, not a harness defect.
 */

/**
 * @return never-return
 */
function alwaysThrows() // T: never-return
{
    throw new \RuntimeException('boom');
}

/**
 * @return never-return
 */
function claimsNeverButReturns() // T: never-return
{
    return 1; // E?: a never-return function must not return a value
}

function afterCall(): int
{
    alwaysThrows();

    return 0; // E?: unreachable after never-return call
}
