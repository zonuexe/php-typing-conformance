<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsExplicitKeyShapeIsList;

/**
 * Explicit-integer-key sealed shape as a `list`.
 *
 * `array{0: string, 1: string}` and `array{string, string}` describe the same values — a
 * two-element list of strings — and both should satisfy a `list<string>` parameter.
 * Per the cross-tool survey (#14939) analyzers diverge on whether the *explicit-key*
 * spelling counts as a list, even though the keyless spelling agrees.
 *
 * Reference: https://github.com/phpstan/phpstan/discussions/14939
 */

/** @param list<string> $list */
function takesList(array $list): void // E<noverify>: NoVerify cannot evaluate the list<string> param type
{
    echo count($list);
}

/** @param array{0: string, 1: string} $shape */
function fromExplicitKeys(array $shape): void // E<noverify>: NoVerify cannot evaluate the array shape param type
{
    takesList($shape); // E<psalm>: Psalm treats the explicit-key shape array{0: string, 1: string} as not directly list<string> and reports ArgumentTypeCoercion // E<pzoom>: same ArgumentTypeCoercion as Psalm. PHPStan, Mago, and Phan accept it (#14939)
}

/** @param array{string, string} $shape */
function fromKeyless(array $shape): void // E<noverify>: NoVerify cannot evaluate the array shape param type
{
    takesList($shape); // the keyless spelling is accepted by every tool, including Psalm
}
