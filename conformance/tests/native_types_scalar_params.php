<?php

declare(strict_types=1);

/**
 * Basic scalar parameter compatibility checks.
 *
 * References:
 * - PHP native scalar parameter types
 */

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

takesInt(1);
takesString('ok');

takesInt('x'); // E: string is not accepted by int parameter
takesString(1); // E: int is not accepted by string parameter
