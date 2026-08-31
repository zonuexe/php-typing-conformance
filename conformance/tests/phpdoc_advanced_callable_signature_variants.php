<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedCallableSignatureVariants;

/**
 * Optional, by-reference, variadic, and Closure callable signatures.
 *
 * The baseline `callable(int): string` case is `callables_docblock_signature`.
 * This file asks whether the extra parameter forms parse and then constrain
 * the callback that is passed.
 *
 * References:
 * - PHPStan callable syntax
 * - Psalm callable types
 */

/**
 * @param callable(int, int=): string $callback
 */
function takesOptionalArgumentCallable(callable $callback): void // T: callable(int, int=): string
{
}

/**
 * @param callable(string &$value): mixed $callback
 */
function takesByReferenceCallable(callable $callback): void // T: callable(string &$value): mixed
{
}

/**
 * @param callable(float ...$values): (int|null) $callback
 */
function takesVariadicCallable(callable $callback): void // T: callable(float ...$values): (int|null)
{
}

/**
 * @param \Closure(int, int): string $callback
 */
function takesClosureSignature(\Closure $callback): void // T: \Closure(int, int): string
{
}

$optional = static function (int $first, int $second = 0): string {
    return (string) ($first + $second);
};
takesOptionalArgumentCallable($optional); // V

$byReference = static function (string &$value): mixed {
    $value = trim($value);

    return $value;
};
takesByReferenceCallable($byReference); // V

$variadic = static function (float ...$values): int {
    return count($values);
};
takesVariadicCallable($variadic); // V

$closure = static fn (int $first, int $second): string => (string) ($first + $second);
takesClosureSignature($closure); // V

takesOptionalArgumentCallable(static fn (string $value): string => $value); // E?: the required parameter is int, not string
takesByReferenceCallable(static fn (string $value): string => $value); // E?: the callback parameter must be passed by reference
takesVariadicCallable(static fn (float $value): int => 1); // E?: a fixed-arity callback is not variadic
takesClosureSignature(static fn (string $first, string $second): string => $first . $second); // E?: Closure parameters are int, not string
