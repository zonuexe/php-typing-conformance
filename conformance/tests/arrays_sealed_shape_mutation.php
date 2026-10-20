<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysSealedShapeMutation;

/**
 * Writing or unsetting a key changes the tracked shape.
 *
 * Arrays are values, so `$shape['extra'] = …` or `unset($shape['foo'])` on a
 * local `array{foo: int}` is not itself an error: the variable just holds a
 * different shape afterwards. The question is whether the tool tracks that
 * shape, which shows when the mutated value is handed back to an
 * `array{foo: int}` receiver. `arrays_shape_sealed_by_default` asks the
 * extra-key question at a call site without the mutation.
 *
 * References:
 * - PHPStan array shapes
 * - Psalm unsealed array shapes, where `...` is the opt-in
 */

/**
 * @param array{foo: int} $shape
 */
function takesFoo(array $shape): void
{
}

/**
 * @param array{foo: int} $shape
 */
function overwritesDeclaredKey(array $shape): void
{
    $shape['foo'] = 2;
    takesFoo($shape); // V: the declared key with a matching type keeps the shape
}

/**
 * @param array{foo: int} $shape
 */
function changesDeclaredKeyType(array $shape): void
{
    $shape['foo'] = 'x';
    takesFoo($shape); // E: foo now holds a string
}

/**
 * @param array{foo: int} $shape
 */
function addsExtraKey(array $shape): void
{
    $shape['extra'] = 'x';
    takesFoo($shape); // E?: a sealed array{foo: int} does not admit the extra key — sealedness is tool-dependent
}

/**
 * @param array{foo: int} $shape
 */
function unsetsRequiredKey(array $shape): void
{
    unset($shape['foo']);
    takesFoo($shape); // E: the required key foo is gone
}
