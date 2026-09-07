<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedClosureSignatureReturnUnion;

use Closure;

/**
 * How `: int|string` binds on a `Closure(...)` PHPDoc signature.
 *
 * PHPStan treats an unparenthesized callable return union as splitting after
 * the first member — `callable(): string|float` is `(callable(): string)|float`.
 * Wrapping the whole spelling, or wrapping only the return, can select the
 * other reading. A closure that returns `int` is valid under both; a closure
 * that returns `string`, and a plain string value, are the discriminants.
 * Native types are omitted so a string argument is not rejected before PHPDoc
 * is considered. `Closure` is imported so the spelling names the class.
 *
 * References:
 * - PHPStan PHPDoc types, Callables
 * - phpstan/phpstan#9268 (`callable(): string|float` vs parentheses)
 */

/**
 * @param Closure(int|string): int|string $foo
 */
function takesBare($foo): void // T: Closure(int|string): int|string
{
}

/**
 * @param (Closure(int|string): int|string) $bar
 */
function takesWrapped($bar): void // T: (Closure(int|string): int|string)
{
}

/**
 * @param Closure(int|string): (int|string) $baz
 */
function takesReturnWrapped($baz): void // T: Closure(int|string): (int|string)
{
}

takesBare(static fn (int|string $x): int => is_int($x) ? $x : 0); // V
takesWrapped(static fn (int|string $x): int => is_int($x) ? $x : 0); // V
takesReturnWrapped(static fn (int|string $x): int => is_int($x) ? $x : 0); // V

takesBare(static fn (int|string $x): string => is_string($x) ? $x : 'x'); // E?: rejected when the return type bound as int only
takesWrapped(static fn (int|string $x): string => is_string($x) ? $x : 'x'); // E?: rejected when the return type bound as int only
takesReturnWrapped(static fn (int|string $x): string => is_string($x) ? $x : 'x'); // E?: rejected when the return type bound as int only

takesBare('hello'); // E?: rejected when the spelling is a Closure, not (Closure)|string
takesWrapped('hello'); // E?: rejected when the spelling is a Closure, not (Closure)|string
takesReturnWrapped('hello'); // E?: rejected when the spelling is a Closure, not (Closure)|string
