<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanNoNamedArguments;

/**
 * Cross-tool handling of `@no-named-arguments`.
 *
 * Callers must use positional arguments so library authors can rename
 * parameters without breaking code. Opinionated API surface rule.
 *
 * References:
 * - PHPStan phpdocs-basics: No named arguments
 *
 * @conformance-kind style
 */

/**
 * @no-named-arguments
 */
function acceptsPositionalOnly(int $value): void // T: @no-named-arguments
{
}

acceptsPositionalOnly(1); // V

// Named form is forbidden by the tag.
acceptsPositionalOnly(value: 2); // E?: named argument disallowed by @no-named-arguments
