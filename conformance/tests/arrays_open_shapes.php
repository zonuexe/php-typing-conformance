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
 * @param array{name: string, ...} $options // E?: some tools cannot parse open array shape PHPDoc syntax
 */
function inspectOpenArrayShape(array $options): void // E?: some tools do not accept open array shape syntax
{
    takesString($options['name']); // E?: some tools do not preserve open array shape required keys precisely
}

inspectOpenArrayShape(['name' => 'demo']);
inspectOpenArrayShape(['name' => 'demo', 'extra' => 123]);
inspectOpenArrayShape(['extra' => 123]); // E?: open array shapes should still require declared keys

/**
 * @param list{string, int, ...} $values // E?: some tools cannot parse open list shape PHPDoc syntax
 */
function inspectOpenListShape(array $values): void // E?: some tools do not accept open list shape syntax
{
    [$name, $count] = $values;

    takesString($name);
    takesInt($count);

    takesString($values[0]); // E?: some tools do not preserve open list shape indexed access precisely
    takesInt($values[1]); // E?: some tools do not preserve open list shape indexed access precisely
}

inspectOpenListShape(['demo', 1]);
inspectOpenListShape(['demo', 1, true]);
inspectOpenListShape([1, 'demo', true]); // E?: open list shapes should still enforce the declared prefix element types
