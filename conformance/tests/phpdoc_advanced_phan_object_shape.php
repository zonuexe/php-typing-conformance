<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhanObjectShape;

/**
 * `stdClass{field: T}` object shapes are a Phan-only notation.
 *
 * Phan parses `stdClass{name: string}` as `StdClassShapeType` and uses the
 * fields for property access, not for argument compatibility. Any `stdClass`
 * is accepted at the call site because the shape type casts as `\stdClass`.
 * Honouring the spelling therefore shows up *inside* the function: `$obj->name`
 * is `string`, so `takesInt($obj->name)` is the enforcement probe. A call-site
 * mismatch is the wrong question — Phan is silent there by design.
 *
 * Other analyzers do not recognize the class-name-plus-braces form (PHPStan
 * parse-errors; Psalm invalid-docblock) and fall back to the native
 * `\stdClass` typehint, where `$obj->name` is untyped.
 *
 * References:
 * - Phan `Language/Type/StdClassShapeType.php` (`stdClass{field: T}`)
 * - Phan `Language/Type.php` shape-component parse for `stdClass`
 */

/**
 * @param stdClass{name: string} $obj
 */
function acceptsObjectShape(\stdClass $obj): void // T: stdClass{name: string}
{
    takesString($obj->name); // V
    takesInt($obj->name); // E?: name is string under stdClass{name: string}, not int
}

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

// Any stdClass is accepted at the call site, even without the field — the
// shape is not an argument check. This only gives the function a value.
$valid = new \stdClass();
$valid->name = 'Ada';
acceptsObjectShape($valid); // V
