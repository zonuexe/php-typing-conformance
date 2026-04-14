<?php

declare(strict_types=1);

namespace Conformance\Tests\EnumsCaseObjectVsBackingScalar;

/**
 * Enum cases are objects, not backing scalar values.
 *
 * References:
 * - PHP native enums
 * - python-typing enum member/value distinction inspiration
 */

enum Status: string
{
    case Active = 'active';
}

function takesString(string $value): void
{
}

takesString('active');
takesString(Status::Active); // E: enum case object is not accepted where string is required
