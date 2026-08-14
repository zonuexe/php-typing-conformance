<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanPureUnlessCallableIsImpure;

/**
 * Cross-tool handling of `@pure-unless-callable-is-impure`.
 *
 * Marks a higher-order function pure only when the named callable argument is
 * pure — the same idea as `array_map`. Available since PHPStan 2.2.
 *
 * References:
 * - PHPStan phpdocs-basics: Conditionally pure functions
 */

/**
 * @param callable(int): int $mapper
 * @param list<int> $values
 * @return list<int>
 * @pure-unless-callable-is-impure $mapper
 */
function mapInts(callable $mapper, array $values): array // T: @pure-unless-callable-is-impure
{
    $result = [];
    foreach ($values as $value) {
        $result[] = $mapper($value);
    }

    return $result;
}

/**
 * @phpstan-impure
 */
function impureMapper(int $n): int
{
    return $n + \random_int(0, 1);
}

/**
 * @phpstan-pure
 */
function pureCaller(): int
{
    // Pure callback: mapInts stays pure and the call is allowed.
    $mapped = mapInts(static fn (int $n): int => $n + 1, [1, 2]); // V

    return $mapped[0];
}

/**
 * @phpstan-pure
 */
function impureCaller(): int
{
    // Impure callback makes mapInts impure under the tag.
    $mapped = mapInts(impureMapper(...), [1, 2]); // E?: impure callback makes mapInts impure inside a pure function

    return $mapped[0];
}
