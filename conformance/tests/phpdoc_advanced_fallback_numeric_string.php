<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNumericString;

/**
 * `numeric-string`
 *
 * `numeric-string` is a refinement of `string`, so a `@return numeric-string`
 * value always satisfies a native `string` parameter — analyzers that do not
 * model it still fall back to `string`. Analyzers that enforce the refinement
 * additionally reject a literal that is not numeric.
 *
 * References:
 * - PHPStan TypeNodeResolver `numeric-string` resolves to string&AccessoryNumericString
 */

/**
 * @return numeric-string
 */
function returnsNumericString() // T: numeric-string
{
    return '123';
}

function acceptsString(string $value): void
{
}

/**
 * @param numeric-string $value
 */
function acceptsNumericString($value): void // T: numeric-string
{
}

// A `numeric-string` value always satisfies a native `string` parameter.
acceptsString(returnsNumericString());

// A literal numeric string satisfies the refined parameter.
acceptsNumericString('123');

// A non-numeric literal: enforcing analyzers reject it, others fall back to
// `string` and accept it.
acceptsNumericString('not numeric'); // E?: non-numeric string should be rejected where numeric-string is required
