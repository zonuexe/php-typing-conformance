<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackIntMaskOf;

/**
 * `int-mask-of<Class::*>` falls back to `int` for cross-boundary compatibility.
 *
 * `int-mask-of<Permissions::*>` describes every bitwise-or combination of the
 * matching class constants, a refinement of `int`, so a `@return int-mask-of`
 * value always satisfies a native `int` parameter. Analyzers that model the
 * mask reject a value that is not a valid combination; others fall back to
 * `int`.
 *
 * References:
 * - PHPStan TypeNodeResolver `int-mask-of` expands the constants to a flag mask
 */

final class Permissions
{
    public const READ = 1;
    public const WRITE = 2;
    public const EXECUTE = 4;
}

/**
 * @return int-mask-of<Permissions::*>
 */
function returnsPermissionMask()
{
    return Permissions::READ | Permissions::EXECUTE;
}

function acceptsInt(int $value): void
{
}

/**
 * @param int-mask-of<Permissions::*> $flags
 */
function acceptsPermissionMask($flags): void
{
}

// An `int-mask-of` value always satisfies a native `int` parameter.
acceptsInt(returnsPermissionMask());

// A valid combination of constants satisfies the refined parameter.
acceptsPermissionMask(Permissions::READ | Permissions::WRITE);

// A value outside the constant mask: mask-aware analyzers reject it, others
// fall back to `int` and accept it.
acceptsPermissionMask(8); // E?: 8 is not a mask of the Permissions constants
