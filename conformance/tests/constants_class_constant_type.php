<?php

declare(strict_types=1);

/**
 * Basic class constant value compatibility checks.
 *
 * References:
 * - PHP class constants
 */

final class HttpStatus
{
    public const int OK = 200;
}

function takesString(string $value): void
{
}

takesString('ok');
takesString(HttpStatus::OK); // E: int constant is not accepted where string is required
