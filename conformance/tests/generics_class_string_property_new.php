<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsClassStringPropertyNew;

/**
 * `new` through a `class-string<T>` property.
 *
 * Instantiating through a `class-string<T>` held in a property:
 * `new ($this->class)()` is a T. The class-string generic case is already
 * covered for parameters (phpdoc_advanced_generic_class_string); this asks
 * whether the property read and the `new (expr)` syntax carry it too.
 *
 * References:
 * - PHPStan / Psalm class-string<T>
 * - https://wiki.php.net/rfc/variable_syntax_tweaks (new with an expression)
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

final class Factory
{
    /** @var class-string<User> */
    public string $class = User::class;
}

function instantiates(Factory $factory): void
{
    $user = new ($factory->class)();
    echo $user->name(); // V
    $made = new ($factory->class)();
    echo $made->missing(); // E: the instance is a User
}
