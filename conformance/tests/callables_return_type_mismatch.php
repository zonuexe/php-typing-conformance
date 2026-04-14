<?php

declare(strict_types=1);

namespace Conformance\Tests\CallablesReturnTypeMismatch;

/**
 * Callable return type compatibility checks.
 *
 * References:
 * - PHPStan callable type syntax
 * - Psalm callable type syntax
 * - python-typing callable compatibility inspiration
 */

/**
 * @param callable(int): string $callback
 */
function takesStringCallback(callable $callback): void
{
}

takesStringCallback(
    static fn (int $value): string => (string) $value,
);

takesStringCallback(
    static fn (int $value): int => $value, // E: callable return type is incompatible
);
