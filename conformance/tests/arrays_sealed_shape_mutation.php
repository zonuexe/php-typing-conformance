<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysSealedShapeMutation;

/**
 * Adding or removing keys on a sealed shape parameter.
 *
 * `arrays_shape_sealed_by_default` asks whether an extra key is admitted at
 * the call site. This file asks the same of mutation after the value is
 * received: `$shape['extra'] = …` and `unset($shape['foo'])` on
 * `array{foo: int}`.
 *
 * References:
 * - PHPStan array shapes
 * - Psalm unsealed array shapes, where `...` is the opt-in
 */

/**
 * @param array{foo: int} $shape
 */
function mutatesSealedShape(array $shape): void
{
    $shape['foo'] = 1; // V: declared key, matching type
    $shape['extra'] = 'x'; // E?: a sealed array{foo: int} does not admit an extra key
    unset($shape['foo']); // E?: unsetting a required key breaks the shape
}

/**
 * @param array{foo: int, ...} $shape
 */
function mutatesUnsealedShape(array $shape): void // T: array{foo: int, ...}
{
    $shape['extra'] = 'x'; // V: unsealed shapes admit extra keys
    $shape['foo'] = 1; // V
}
