<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedLiteralValues;

/**
 * Literal floats and class-constant value types in PHPDoc.
 *
 * References:
 * - PHPStan literal types and constant types
 * - Psalm literal and constant types
 */

final class Status
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';
}

/**
 * @param 1.5 $value
 */
function takesLiteralFloat(float $value): void // T: 1.5
{
}

/**
 * @param Status::ACTIVE $value
 */
function takesActiveStatus(string $value): void // T: Status::ACTIVE
{
}

/**
 * @param Status::* $value
 */
function takesAnyStatus(string $value): void // T: Status::*
{
}

takesLiteralFloat(1.5); // V
takesActiveStatus(Status::ACTIVE); // V
takesAnyStatus(Status::ACTIVE); // V
takesAnyStatus(Status::INACTIVE); // V

takesLiteralFloat(2.5); // E?: the float literal does not match 1.5
takesActiveStatus(Status::INACTIVE); // E?: the class constant value is not active
takesAnyStatus('unknown'); // E?: the value is not one of Status constants
