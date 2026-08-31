<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedObjectShapeVariants;

/**
 * Optional properties and writable object shapes.
 *
 * The sealed required-property case is `phpdoc_advanced_fallback_object_shape`.
 * This file asks two extras: whether `bar?: string` may be omitted, and whether
 * intersecting the shape with `\stdClass` still accepts a matching object.
 *
 * References:
 * - PHPStan object shapes
 * - Psalm object properties
 */

final class WithOptionalProperty
{
    public int $foo = 1;

    public string $bar = 'optional';
}

final class WithoutOptionalProperty
{
    public int $foo = 1;
}

final class WrongFooType
{
    public string $foo = 'wrong';
}

/**
 * @param object{foo: int, bar?: string} $shape
 */
function takesOptionalObjectShape(object $shape): void // T: object{foo: int, bar?: string}
{
}

/**
 * @param object{foo: int}&\stdClass $shape
 */
function takesWritableObjectShape(object $shape): void // T: object{foo: int}&\stdClass
{
}

takesOptionalObjectShape(new WithOptionalProperty()); // V
takesOptionalObjectShape(new WithoutOptionalProperty()); // V
takesOptionalObjectShape((object) ['foo' => 1]); // V
takesWritableObjectShape((object) ['foo' => 1]); // V

takesOptionalObjectShape(new WrongFooType()); // E?: foo must have type int
takesWritableObjectShape((object) ['foo' => 'wrong']); // E?: foo must have type int
