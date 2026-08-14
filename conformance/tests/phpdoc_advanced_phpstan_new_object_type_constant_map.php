<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanNewObjectTypeConstantMap;

/**
 * `new<CONSTANT[T]>`
 *
 * The shape `new<>` was introduced for: a constant map from names to classes,
 * a template parameter bound by `key-of<>` over that map, and an offset into
 * the map to pick the class the caller asked for. PHPStan's own "Generics by
 * examples" writes it with a class constant; a plain global constant is the
 * same question with one less lookup.
 *
 * Three separate features have to land for the return type to resolve, so a
 * miss here is not automatically a miss on `new<>` -- read it together with
 * phpdoc_advanced_phpstan_new_object_type, which probes the spelling on its
 * own, and with the key-of tests.
 *
 * References:
 * - PHPStan "Generics by examples", container with static services
 * - PHPStan TypeNodeResolver, `new` resolves to NewObjectType
 */

const DATETIME_CLASSES = [
    'immutable' => \DateTimeImmutable::class,
    'mutable' => \DateTime::class,
];

function takesImmutable(\DateTimeImmutable $value): void
{
}

function takesMutable(\DateTime $value): void
{
}

/**
 * @template T of key-of<DATETIME_CLASSES>
 * @param T $type
 * @return new<DATETIME_CLASSES[T]>
 */
function createNow(string $type = 'immutable'): \DateTimeInterface // T: new<DATETIME_CLASSES[T]>
{
    return new (DATETIME_CLASSES[$type]); // V
}

// Each key names its own class, the default argument included.
takesImmutable(createNow()); // V
takesImmutable(createNow('immutable')); // V
takesMutable(createNow('mutable')); // V

// Swapping the two is what the mapping exists to catch.
takesMutable(createNow('immutable')); // E?: 'immutable' maps to DateTimeImmutable, not DateTime
takesImmutable(createNow('mutable')); // E?: 'mutable' maps to DateTime, not DateTimeImmutable
