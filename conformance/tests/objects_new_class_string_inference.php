<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsNewClassStringInference;

/**
 * `new $class()` when `$class` is a union of known `class-string` values.
 *
 * `generics_class_string_property_new` covers a `class-string<T>` property;
 * `objects_class_constant_on_instance` covers `$object::class`. This file
 * asks whether a local union `User::class|Admin::class` instantiates as
 * `User|Admin` rather than `object`.
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

class Admin extends User
{
    public function role(): string
    {
        return 'admin';
    }
}

function takesUser(User $value): void
{
}

function fromSingleClassString(): void
{
    $class = User::class;
    $instance = new $class();
    echo $instance->name(); // V
    $other = new $class();
    echo $other->missing(); // E: the instance is a User
}

function fromUnionClassString(): void
{
    $class = \random_int(0, 1) === 1 ? User::class : Admin::class;
    $instance = new $class();
    takesUser($instance); // V: User|Admin is a User
    echo $instance->role(); // E?: User|Admin does not always have role()
}
