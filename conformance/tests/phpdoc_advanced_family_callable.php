<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyCallable;

/**
 * Callable refinements (`callable-string`, `callable-object`)
 *
 * `callable-string`, `callable-array` and `callable-object` refine a string,
 * array or object to one that is callable. Analyzers that model them reject a
 * non-callable value; others fall back to the base type and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver callable-string / callable-array / callable-object / pure-callable
 */

final class Invokable
{
    public function __invoke(): void
    {
    }
}

final class NotInvokable
{
}

/** @param callable-string $value */
function acceptsCallableString($value): void
{
}

/** @param callable-object $value */
function acceptsCallableObject($value): void
{
}

acceptsCallableString('strlen');
acceptsCallableString('definitely_not_a_function'); // E?: not a callable-string

acceptsCallableObject(new Invokable());
acceptsCallableObject(new NotInvokable()); // E?: object without __invoke is not a callable-object
