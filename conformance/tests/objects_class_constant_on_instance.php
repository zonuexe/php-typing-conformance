<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsClassConstantOnInstance;

/**
 * `new` through `$object::class`.
 *
 * `$object::class` is the class-string of the object's class, so
 * `new ($object::class)()` builds another instance of it. A tool that types
 * `$object::class` as plain string ends up with `object` and loses every
 * member check on the new instance.
 *
 * References:
 * - https://wiki.php.net/rfc/class_name_literal_on_object
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

function clonesClass(User $user): void
{
    $class = $user::class;
    echo (new $class())->name(); // V
    $sameClass = $user::class;
    echo (new $sameClass())->missing(); // E: the new instance is a User
}
