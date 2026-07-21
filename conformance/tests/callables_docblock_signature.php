<?php

declare(strict_types=1);

namespace Conformance\Tests\CallablesDocblockSignature;

/**
 * Callable signature compatibility in PHPDoc.
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
