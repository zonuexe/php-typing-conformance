<?php

declare(strict_types=1);

namespace Conformance\Tests\CallablesClosureParamFromArrayCallback;

/**
 * Closure parameter types from array_map / array_filter.
 *
 * An untyped closure parameter takes its type from the array the callback is
 * applied to: `array_map(fn ($x) => …, $listOfUsers)` sees each `$x` as a
 * User, so a method User lacks is an error inside the callback.
 *
 * The stubs for `array_map` and `array_filter` carry the callable signature
 * (`callable(T): U`, `callable(T): bool`); a tool that only reads the native
 * `callable` declaration leaves `$x` as mixed and stays silent.
 *
 * References:
 * - PHPStan / Psalm array_map and array_filter stubs
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

/** @param list<User> $users */
function mapsUsers(array $users): void
{
    echo implode(',', array_map(fn ($user) => $user->name(), $users)); // V
    echo count(array_map(fn ($user) => $user->missing(), $users)); // E: User has no missing()
}

/** @param list<User> $users */
function filtersUsers(array $users): void
{
    echo count(array_filter($users, fn ($user) => $user->name() !== '')); // V
    echo count(array_filter($users, fn ($user) => $user->missing() !== '')); // E: User has no missing()
}

/** @param list<User> $users */
function mapsUsersWithClosure(array $users): void
{
    echo count(array_map(function ($user): string {
        return $user->missing(); // E: User has no missing()
    }, $users));
}
