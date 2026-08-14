<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysListShapeDestructuring;

/**
 * List shape support for positional destructuring and indexed access.
 *
 * References:
 * - Psalm list shape syntax
 * - python-typing tuple-style positional element inspiration
 */

function takesBool(bool $value): void
{
}

function takesFloat(float $value): void
{
}

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

/**
 * @param list{bool, float, string, int} $list
 */
function inspectListShape(array $list): void // T: list{bool, float, string, int}
{
    [$a, $b, $c, $d] = $list;

    takesBool($a);
    takesFloat($b);
    takesString($c);
    takesInt($d);

    takesBool($list[0]); // E?: some tools do not preserve list shape indexed access precisely
    takesFloat($list[1]); // E?: some tools do not preserve list shape indexed access precisely
    takesString($list[2]); // E?: some tools do not preserve list shape indexed access precisely
    takesInt($list[3]); // E?: some tools do not preserve list shape indexed access precisely

    takesInt($a); // E?: destructured element 0 should stay bool
    takesString($list[0]); // E?: indexed element 0 should stay bool
    takesBool($list[3]); // E?: indexed element 3 should stay int
}

inspectListShape([true, 1.5, 'x', 2]); // V
inspectListShape([true, 1.5, 2, 'x']); // E?: call-site list shape should preserve positional element types
