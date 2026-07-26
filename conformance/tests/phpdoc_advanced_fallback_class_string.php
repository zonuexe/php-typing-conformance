<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackClassString;

/**
 * `class-string`
 *
 * `class-string` is a refinement of `string`, so a `@return class-string`
 * value always satisfies a native `string` parameter — analyzers that do not
 * model it still fall back to `string`. Analyzers that enforce the refinement
 * additionally reject a literal that is not a known class name.
 *
 * References:
 * - PHPStan TypeNodeResolver `class-string` resolves to ClassStringType
 */

/**
 * @return class-string
 */
function returnsClassString() // T: class-string
{
    return \stdClass::class;
}

function acceptsString(string $value): void
{
}

/**
 * @param class-string $value
 */
function acceptsClassString($value): void // T: class-string
{
}

// A `class-string` value always satisfies a native `string` parameter.
acceptsString(returnsClassString());

// A real class name satisfies the refined parameter.
acceptsClassString(\stdClass::class);

// A non-class literal string: enforcing analyzers reject it, others fall back
// to `string` and accept it.
acceptsClassString('not a class name'); // E?: non-class-string should be rejected where class-string is required
