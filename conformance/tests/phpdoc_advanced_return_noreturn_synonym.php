<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedReturnNoreturnSynonym;

/**
 * `noreturn` is a non-standard alias for `never`.
 *
 * `noreturn` was one of the rejected name candidates for the bottom type; PHP
 * adopted `never` instead. Recognizing `noreturn` is therefore not a
 * compatibility requirement — this case records two things: that PHPStan and
 * mir keep the alias resolvable at all, and whether the resolved alias actually
 * reaches the engine behind `never` — rejecting a `return` statement inside the
 * function, and treating the code after a call to it as unreachable. Other
 * analyzers treat the unknown name as an undefined type. Neither behavior is a
 * defect.
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

/**
 * @return noreturn
 */
function claimsNoreturnButReturns() // T: noreturn
{
    return 1; // E?: a noreturn function must not return a value
}

function afterNoreturnCall(): int
{
    alwaysThrows();

    return 1; // E?: code after a call to a noreturn function is unreachable
}
