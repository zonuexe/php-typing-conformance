<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysFunctionReturnTypes;

/**
 * Element types through array_* function returns.
 *
 * The array functions carry the element type of their argument through:
 * `array_shift`, `reset`, `array_merge` and `array_filter` on a `list<User>`
 * all yield Users, not mixed. A method User lacks is then an error.
 *
 * References:
 * - PHPStan / Psalm function maps for the array_* functions
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

/** @param list<User> $users */
function shifts(array $users): void
{
    echo array_shift($users)?->name(); // V
}

/** @param list<User> $users */
function shiftsMissing(array $users): void
{
    echo array_shift($users)?->missing(); // E: array_shift() returns User|null
}

/** @param list<User> $users */
function resets(array $users): void
{
    $first = reset($users);
    if ($first !== false) {
        echo $first->name(); // V
    }
    $head = reset($users);
    if ($head !== false) {
        echo $head->missing(); // E: reset() returns User|false
    }
}

/**
 * @param list<User> $left
 * @param list<User> $right
 */
function merges(array $left, array $right): void
{
    $merged = array_merge($left, $right);
    echo $merged[0]->name(); // V
    $combined = array_merge($left, $right);
    echo $combined[0]->missing(); // E: array_merge() keeps the User element type
}

/** @param list<User> $users */
function filters(array $users): void
{
    foreach (array_filter($users, fn (User $user): bool => $user->name() !== '') as $kept) {
        echo $kept->name(); // V
    }
    foreach (array_filter($users, fn (User $user): bool => $user->name() !== '') as $remaining) {
        echo $remaining->missing(); // E: array_filter() keeps the User element type
    }
}
