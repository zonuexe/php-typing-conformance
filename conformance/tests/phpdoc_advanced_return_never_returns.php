<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedReturnNeverReturns;

/**
 * `never-returns` as a bottom-type spelling.
 *
 * PHPStan documents `never`, `never-return`, and `never-returns` as
 * equivalent. `noreturn` is covered separately; this file probes the
 * `never-returns` alias.
 *
 * References:
 * - PHPStan phpdoc-types: Bottom type
 *
 * Tools that do not know the alias may treat `never-returns` as a class name
 * on the declaration line; that is expected, not a harness defect (same
 * pattern as `noreturn`).
 */

/**
 * @return never-returns
 */
function alwaysThrows() // T: never-returns
{
    throw new \RuntimeException('boom');
}

/**
 * @return never-returns
 */
function claimsNeverButReturns() // T: never-returns
{
    return 1; // E?: a never-returns function must not return a value
}

function afterCall(): int
{
    alwaysThrows(); // V

    return 0; // E?: unreachable after never-returns call
}
