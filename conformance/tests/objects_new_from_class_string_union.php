<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsNewFromClassStringUnion;

/**
 * `new $class()` when `$class` is a union of known class names.
 *
 * `generics_class_string_property_new` covers a `class-string<T>` property;
 * `objects_class_constant_on_instance` covers a single class name taken from
 * `$object::class`. This file asks whether a local union
 * `User::class|Admin::class` instantiates as `User|Admin` rather than
 * `object`. Admin extends User, so `name()` is on every member and
 * `missing()` on none — the probe does not depend on how strictly a tool
 * checks methods present on only one member of a union.
 *
 * Source: jorgsowa/mir v0.87.0.
 *
 * References:
 * - https://github.com/jorgsowa/mir/releases/tag/v0.87.0
 */

class User
{
    public function name(): string
    {
        return 'user';
    }
}

final class Admin extends User
{
}

function takesUser(User $value): void
{
}

function fromUnionClassString(): void
{
    $class = \random_int(0, 1) === 1 ? User::class : Admin::class;
    $instance = new $class(); // E?[noise]: UnsafeInstantiation on the non-final User
    takesUser($instance); // V: User|Admin is a User
    echo $instance->name(); // V
    $other = new $class(); // E?[noise]: UnsafeInstantiation on the non-final User
    echo $other->missing(); // E: neither User nor Admin has missing()
}
