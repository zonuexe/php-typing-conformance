<?php

declare(strict_types=1);

/**
 * Basic union member compatibility checks.
 *
 * References:
 * - PHP native union types
 */

function takesIntOrString(int|string $value): void
{
}

function takesNullableInt(?int $value): void
{
}

takesIntOrString(1);
takesIntOrString('ok');
takesNullableInt(null);
takesNullableInt(2);

takesIntOrString(1.5); // E: float is not accepted by int|string
takesNullableInt('x'); // E: string is not accepted by ?int
