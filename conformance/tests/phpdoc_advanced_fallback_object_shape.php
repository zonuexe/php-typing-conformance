<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackObjectShape;

/**
 * `object{foo: int}`
 *
 * The object counterpart of an array shape: any object exposing a public `foo`
 * of type `int` satisfies it, whatever its class. Two widenings are worth
 * telling apart, because they go wrong in opposite directions — an analyzer
 * that drops the braces and keeps plain `object` accepts everything, while one
 * that reads the shape as `\stdClass` rejects a sound instance of a named
 * class. The first shows up as silence on the probes, the second as a
 * complaint about the calls that are meant to pass.
 *
 * References:
 * - PHPStan object shapes, `object{foo: int, bar?: string}`
 * - Psalm "Object properties", `@param object{foo: string} $obj`
 * - Intelephense Type-System, object shapes over dynamic properties
 */

final class Reading
{
    public int $foo = 1;
}

final class Mistyped
{
    public string $foo = 'one';
}

final class Unrelated
{
    public int $bar = 1;
}

/**
 * @param object{foo: int} $shape
 */
function takesObjectShape(object $shape): void // T: object{foo: int}
{
}

// Any class carrying the declared property qualifies, `stdClass` included.
takesObjectShape(new Reading());
takesObjectShape((object) ['foo' => 1]);

// The property is an int,
takesObjectShape(new Mistyped()); // E?: foo is string, not int

// and it has to be there at all.
takesObjectShape(new Unrelated()); // E?: there is no foo property to satisfy the shape
