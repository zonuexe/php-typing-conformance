<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmStringableObject;

/**
 * `stringable-object` is a Psalm-only object refinement.
 *
 * `stringable-object` matches any object exposing `__toString()`. Psalm models
 * it structurally; other analyzers do not recognize the keyword and fall back
 * to accepting any object (or report an unknown type).
 *
 * References:
 * - Psalm `stringable-object` maps to an object-with-__toString shape
 */

final class WithToString
{
    public function __toString(): string
    {
        return 'x';
    }
}

final class WithoutToString
{
}

/**
 * @param stringable-object $obj
 */
function acceptsStringable($obj): void // T: stringable-object
{
}

// An object with __toString satisfies the parameter.
acceptsStringable(new WithToString());

// An object without __toString is rejected by analyzers that model it.
acceptsStringable(new WithoutToString()); // E?: object without __toString is not a stringable-object
