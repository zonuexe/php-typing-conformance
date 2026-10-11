<?php

declare(strict_types=1);

namespace Conformance\Tests\CallablesImmediatelyInvokedClosure;

/**
 * Return type of an immediately invoked closure.
 *
 * An immediately invoked closure or arrow function, `(fn (): T => …)()`,
 * evaluates to the closure's return type. The control assigns the closure
 * first and calls the variable, which most tools already handle.
 *
 * References:
 * - https://www.php.net/manual/en/functions.arrow.php
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

function assignedThenCalled(): void
{
    $make = fn (): User => new User();
    echo $make()->name(); // V
    $build = fn (): User => new User();
    echo $build()->missing(); // E: the call returns a User
}

function invokedArrow(): void
{
    $user = (fn (): User => new User())();
    echo $user->name(); // V
    $made = (fn (): User => new User())();
    echo $made->missing(); // E: the call returns a User
}

function invokedClosure(): void
{
    $built = (function (): User {
        return new User();
    })();
    echo $built->missing(); // E: the call returns a User
}
