<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedReturnNoreturnSynonym;

/**
 * `noreturn` is a non-standard alias for `never`.
 *
 * `noreturn` was one of the rejected name candidates for the bottom type; PHP
 * adopted `never` instead. Recognizing `noreturn` is therefore not a
 * compatibility requirement — this case only records that PHPStan and mir keep
 * it as a legacy alias, while other analyzers treat the unknown name as an
 * undefined type. Neither behavior is a defect.
 *
 * References:
 * - never RFC (name candidates incl. `noreturn`): https://wiki.php.net/rfc/noreturn_type
 * - PHPStan TypeNodeResolver `noreturn` case resolves like `never`
 */

/**
 * @return noreturn
 */
function alwaysThrows() // T: noreturn
{
    throw new \RuntimeException('boom');
}
