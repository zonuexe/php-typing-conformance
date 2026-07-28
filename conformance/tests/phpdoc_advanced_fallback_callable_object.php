<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackCallableObject;

/**
 * `callable-object`
 *
 * An object with `__invoke()`, which the engine will accept anywhere a callable
 * is expected. A `Closure` qualifies without declaring the method. Analyzers
 * that model the refinement reject an object without it; others fall back to
 * plain `object` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver `callable-object` resolves to object&CallableType
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

/**
 * @return callable-object
 */
function returnsCallableObject() // T: callable-object
{
    return new Invokable();
}

function acceptsObject(object $value): void
{
}

/**
 * @param callable-object $value
 */
function acceptsCallableObject($value): void // T: callable-object
{
}

// A `callable-object` value always satisfies a native `object` parameter.
acceptsObject(returnsCallableObject());

// An object with __invoke() satisfies the parameter, and so does a Closure.
acceptsCallableObject(new Invokable());
acceptsCallableObject(static fn (): int => 1);

// An object without __invoke() does not.
acceptsCallableObject(new NotInvokable()); // E?: an object without __invoke() is not a callable-object
