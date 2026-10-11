<?php

declare(strict_types=1);

namespace Conformance\Tests\CallablesFirstClassMethodInvocation;

/**
 * Return type of an invoked first-class method callable.
 *
 * Invoking a first-class callable created from a method,
 * `$f = $object->method(...); $f()`, returns the method's return type —
 * the same as for a function-based `fn(...)`, which is the control here.
 *
 * References:
 * - https://wiki.php.net/rfc/first_class_callable_syntax
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

function findUser(): User
{
    return new User();
}

function functionCallable(): void
{
    $find = findUser(...);
    echo $find()->name(); // V
    $lookup = findUser(...);
    echo $lookup()->missing(); // E: the call returns a User
}

function methodCallable(Repository $repository): void
{
    $find = $repository->find(...);
    echo $find()->name(); // V
    $lookup = $repository->find(...);
    echo $lookup()->missing(); // E: the call returns a User
}
