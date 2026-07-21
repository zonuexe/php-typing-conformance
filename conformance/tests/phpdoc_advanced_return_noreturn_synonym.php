<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedReturnNoreturnSynonym;

/**
 * `noreturn` PHPDoc synonym is compatible with native `never`.
 *
 * `noreturn` is PHPStan's spelling of the `never` bottom type. An analyzer that
 * recognizes the synonym accepts a `@return noreturn` function that always
 * terminates; one that does not treats `noreturn` as a class name and reports
 * it as an unknown type.
 *
 * References:
 * - PHPStan TypeNodeResolver `noreturn` case resolves like `never`
 */

/**
 * @return noreturn
 */
function alwaysThrows()
{
    throw new \RuntimeException('boom');
}
