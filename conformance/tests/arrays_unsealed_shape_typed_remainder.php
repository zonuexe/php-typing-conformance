<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysUnsealedShapeTypedRemainder;

/**
 * `array{name: string, age: int, ...<Foo>}`
 *
 * A trailing `...<T>` unseals an array shape and types every extra value as
 * `T` (the extra key type is implied `array-key`). Bare `...` is
 * `arrays_unsealed_shape`; this file asks whether the remainder type is kept
 * — both when an extra key is passed in, and when a value is read back out
 * of one.
 *
 * References:
 * - PHPStan 2.2 unsealed array shapes, `array{foo: int, ...<Bar>}`
 *   https://phpstan.org/blog/phpstan-2-2-unsealed-array-shapes-safer-array-keys
 * - Psalm "Unsealed array and list shapes", `...<TKey, TValue>`
 * - Intelephense Type-System, extra elements of form `...<TKey, TValue>`
 */

final class Foo
{
}

function takesFoo(Foo $value): void
{
}

function takesString(string $value): void
{
}

/**
 * @param array{name: string, age: int, ...<Foo>} $row
 */
function acceptsRow(array $row): void // T: array{name: string, age: int, ...<Foo>}
{
}

/**
 * @param array{name: string, age: int, ...<Foo>} $row
 */
function inspectRow(array $row): void // T: array{name: string, age: int, ...<Foo>}
{
    // Extra keys are Foo. The isset() keeps "the offset exists" out of the
    // question; what remains is the remainder type.
    if (isset($row['extra'])) {
        takesFoo($row['extra']); // Q?: remainder values are Foo
        takesString($row['extra']); // E?: extra is Foo, not string
    }
}

// The declared keys on their own satisfy the shape.
acceptsRow(['name' => 'Ada', 'age' => 36]); // V

// So does an extra key whose value is Foo.
acceptsRow(['name' => 'Ada', 'age' => 36, 'extra' => new Foo()]); // V
acceptsRow(['name' => 'Ada', 'age' => 36, 'a' => new Foo(), 'b' => new Foo()]); // V
inspectRow(['name' => 'Ada', 'age' => 36, 'extra' => new Foo()]); // V

// `...<Foo>` constrains the extras, not the declared keys. An analyzer that
// read this as bare `...` accepts the string extra; one that sealed the
// shape rejects the Foo extras above as well.
acceptsRow(['name' => 'Ada', 'age' => 36, 'extra' => 'nope']); // E?: extra is Foo, not string

// The listed keys stay required and keep their types.
acceptsRow(['name' => 'Ada']); // E?: age is still required
acceptsRow(['extra' => new Foo()]); // E?: extras do not stand in for name and age
acceptsRow(['name' => 1, 'age' => 36]); // E?: name is string, not int
