<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanNewObjectType;

/**
 * `new<TClass>`
 *
 * A PHPStan-only spelling: it takes a class-string and stands for an instance
 * of it, so a factory can return the class it was handed without the caller
 * losing the type. Analyzers that do not model it widen the return to the
 * native `object`, which is not silent — the calls that ought to pass start
 * failing instead, so the first two calls carry the finding as much as the
 * probes do.
 *
 * `class-string<T>` to `T` says the same thing in the spelling the whole
 * ecosystem models; the generics_template_* rows carry that, so a tool failing
 * here and there alike is missing template inference rather than `new<>`.
 *
 * References:
 * - PHPStan TypeNodeResolver, `new` resolves to NewObjectType
 * - PHPStan "Generics by examples", container with static services
 */

function takesImmutable(\DateTimeImmutable $value): void
{
}

function takesMutable(\DateTime $value): void
{
}

/**
 * @template TClass of string
 * @param TClass $class
 * @return new<TClass>
 */
function instantiate(string $class): object // T: new<TClass>
{
    return new $class(); // V
}

// The class-string handed in comes back as an instance of that class.
takesImmutable(instantiate(\DateTimeImmutable::class)); // V
takesMutable(instantiate(\DateTime::class)); // V

// And not as an instance of the other one.
takesMutable(instantiate(\DateTimeImmutable::class)); // E?: new<DateTimeImmutable::class> is not a DateTime
takesImmutable(instantiate(\DateTime::class)); // E?: new<DateTime::class> is not a DateTimeImmutable
