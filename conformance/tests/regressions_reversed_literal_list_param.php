<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsReversedLiteralListParam;

/**
 * A reversed-key array literal passed to a `list{...}` parameter.
 *
 * `[1 => 'x', 0 => 'y']` is not a list at runtime — `array_is_list()` returns false
 * because the keys appear out of order. Passing it where a `list{string, string}` is
 * required should be rejected, yet analyzers diverge: per the cross-tool survey (#14939)
 * Phan and Psalm reject the literal while PHPStan and Mago accept it.
 *
 * The rejection is anchored in two different places depending on the tool: at the call
 * site that passes the literal, or at the `@param` that declares the contract it breaks.
 * Both name the same violation, so both lines carry the expectation.
 *
 * Reference: https://github.com/phpstan/phpstan/discussions/14939
 */

/**
 * @param list{string, string} $list // E[reversed_literal+]: the reversed-key literal must be rejected; blaming the declared contract is one valid anchor
 */
function takesTwoList(array $list): void // E<noverify>: NoVerify cannot evaluate the list{string, string} param type
{
    echo $list[0] . $list[1];
}

function caller(): void
{
    takesTwoList([1 => 'x', 0 => 'y']); // E[reversed_literal+]: the reversed-key literal must be rejected; blaming the call site is the other valid anchor (#14939)
}
