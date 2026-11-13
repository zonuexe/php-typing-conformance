<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmAssertObject;

/**
 * Object-type assertion form `@psalm-assert SomeObjectType $foo`.
 *
 * Any class name is a valid assertion. After the call, `$value` is `Widget`,
 * so a string check is dead. Tools that ignore the tag leave `mixed`.
 *
 * References:
 * - Psalm assertion_syntax.md: Object type assertions
 */

final class Widget
{
    public function label(): string
    {
        return 'w';
    }
}

/**
 * @psalm-assert Widget $value
 */
function assertWidget(mixed $value): void // T: @psalm-assert Widget
{
    if (!$value instanceof Widget) {
        throw new \InvalidArgumentException('not Widget');
    }
}

function example(mixed $value): void
{
    assertWidget($value);

    echo $value->label(); // V

    echo \is_string($value) ? 'string' : 'widget'; // E?: after @psalm-assert Widget, is_string() is always false
}
