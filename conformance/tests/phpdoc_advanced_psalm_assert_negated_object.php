<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmAssertNegatedObject;

/**
 * Negated object assertion `@psalm-assert !SomeObjectType $bar`.
 *
 * Starting from `Widget|Gadget`, after the call only `Gadget` remains, so
 * `instanceof Widget` is dead. Tools that parse `!Widget` as an unknown type
 * (or ignore the tag) leave the union intact.
 *
 * References:
 * - Psalm assertion_syntax.md: Negated assertions (`!SomeObjectType`)
 */

final class Widget
{
}

final class Gadget
{
    public function ping(): string
    {
        return 'p';
    }
}

/**
 * @psalm-assert !Widget $value
 */
function assertNotWidget(Widget|Gadget $value): void // T: @psalm-assert !Widget
{
    if ($value instanceof Widget) {
        throw new \InvalidArgumentException('must not be Widget');
    }
}

function takesGadget(Gadget $value): void
{
}

function example(Widget|Gadget $value): void
{
    assertNotWidget($value);

    takesGadget($value); // V

    echo $value instanceof Widget ? 'widget' : 'gadget'; // E?: after @psalm-assert !Widget, instanceof Widget is always false
}
