<?php

declare(strict_types=1);

/**
 * Basic callable signature compatibility checks expressed in PHPDoc.
 *
 * References:
 * - PHPStan callable type syntax
 * - Psalm callable type syntax
 */

/**
 * @param callable(int): string $callback
 */
function takesIntToStringCallable(callable $callback): void
{
}

takesIntToStringCallable(
    static fn (int $value): string => (string) $value,
);

takesIntToStringCallable(
    static fn (string $value): string => $value, // E: callable parameter type is incompatible
);
