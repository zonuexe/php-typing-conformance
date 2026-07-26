<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhanObjectShape;

/**
 * `stdClass{field: T}` object shapes are a Phan-only notation.
 *
 * Phan supports object shapes on `stdClass`, paralleling `array{...}` shapes.
 * Other analyzers do not recognize the syntax and fall back to a plain
 * `stdClass` (or report an unknown type).
 *
 * References:
 * - Phan Type/StdClassShapeType.php (`stdClass{field: T}`)
 */

/**
 * @param stdClass{name: string} $obj
 */
function acceptsObjectShape(\stdClass $obj): void // T: stdClass{name: string}
{
}

// A stdClass carrying the declared field satisfies the shape.
$valid = new \stdClass();
$valid->name = 'Ada';
acceptsObjectShape($valid);

// A stdClass with the field set to the wrong type violates the shape.
$invalid = new \stdClass();
$invalid->name = 123;
acceptsObjectShape($invalid); // E?: name must be string in stdClass{name: string}
