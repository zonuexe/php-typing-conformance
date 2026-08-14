<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysOpenShapes;

/**
 * Open array and list shape support.
 *
 * References:
 * - Psalm unsealed array and list shapes
 * - python-typing tuple-style prefix compatibility inspiration
 */

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

/**
 * @param array{name: string, ...} $options
 */
function inspectOpenArrayShape(array $options): void // T: array{name: string, ...}
{
    takesString($options['name']); // E?: some tools do not preserve open array shape required keys precisely
}

inspectOpenArrayShape(['name' => 'demo']); // V
inspectOpenArrayShape(['name' => 'demo', 'extra' => 123]); // V
inspectOpenArrayShape(['extra' => 123]); // E?: open array shapes should still require declared keys

/**
 * @param list{string, int, ...} $values
 */
function inspectOpenListShape(array $values): void // T: list{string, int, ...}
{
    [$name, $count] = $values;

    takesString($name);
    takesInt($count);

    takesString($values[0]); // E?: some tools do not preserve open list shape indexed access precisely
    takesInt($values[1]); // E?: some tools do not preserve open list shape indexed access precisely
}

inspectOpenListShape(['demo', 1]); // V
inspectOpenListShape(['demo', 1, true]); // V
inspectOpenListShape([1, 'demo', true]); // E?: open list shapes should still enforce the declared prefix element types
