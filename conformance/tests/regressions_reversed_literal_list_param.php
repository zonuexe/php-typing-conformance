<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsReversedLiteralListParam;

/**
 * A reversed-key array literal passed to a `list{...}` parameter.
 *
 * `[1 => 'x', 0 => 'y']` is not a list at runtime — `array_is_list()` returns false
 * because the keys appear out of order. Passing it where a `list{string, string}` is
 * required should be rejected, yet analyzers diverge: per the cross-tool survey (#14939)
 * Phan rejects the literal while PHPStan, Psalm, and Mago accept it.
 *
 * Reference: https://github.com/phpstan/phpstan/discussions/14939
 */

/** @param list{string, string} $list */
function takesTwoList(array $list): void // E<noverify>: NoVerify cannot evaluate the list{string, string} param type
{
    echo $list[0] . $list[1];
}

function caller(): void
{
    takesTwoList([1 => 'x', 0 => 'y']); // E<psalm>: rejects the reversed-key literal as not a list (ArgumentTypeCoercion) // E<phan>: PhanTypeMismatchArgument — array{1:'x',0:'y'} is not list<mixed>. PHPStan and Mago accept the literal despite it not being a list at runtime (#14939)
}
