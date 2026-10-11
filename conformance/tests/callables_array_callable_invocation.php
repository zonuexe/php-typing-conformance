<?php

declare(strict_types=1);

namespace Conformance\Tests\CallablesArrayCallableInvocation;

/**
 * Return type of an invoked array callable.
 *
 * Invoking a `[$object, 'method']` array callable directly returns that
 * method's return type. The array literal names its target exactly, so a tool
 * can resolve the call the same way it resolves `$object->method()`.
 *
 * References:
 * - https://www.php.net/manual/en/language.types.callable.php
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

final class Repository
{
    public function find(): User
    {
        return new User();
    }
}

function invokesArrayCallable(Repository $repository): void
{
    $find = [$repository, 'find'];
    echo $find()->name(); // V
    $lookup = [$repository, 'find'];
    echo $lookup()->missing(); // E: the call returns a User
}
