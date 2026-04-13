<?php

declare(strict_types=1);

namespace Conformance\Tests\EnumsBackedCases;

/**
 * Basic backed enum compatibility checks.
 *
 * References:
 * - PHP native enums
 */

enum Status: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}

function takesStatus(Status $status): void
{
}

takesStatus(Status::Active);
takesStatus('active'); // E: raw string is not accepted where Status is required
