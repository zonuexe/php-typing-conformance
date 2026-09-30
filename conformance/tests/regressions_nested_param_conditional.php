<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsNestedParamConditional;

/**
 * Nested `($key is Model ? … : ($key is Arrayable ? …))` must narrow `$key`
 * in the inner condition to the else of the outer one.
 *
 * `find(?User)`: the outer `is Model` takes the `User` arm (`User|null`). The
 * inner `is Arrayable` only sees what remains (`null`), which is not
 * `Arrayable`, so `static` (the collection) is not in the result. PHPStan
 * 2.2.15 resolved the inner condition against the whole `?User`, `User`
 * matched `Arrayable`, and the return became `Collection<User>|User|null`.
 *
 * Source: phpstan/phpstan#15296, phpstan/phpstan-src#6586 (2.2.16).
 *
 * References:
 * - https://github.com/phpstan/phpstan/issues/15296
 * - https://github.com/phpstan/phpstan-src/pull/6586
 */

interface Arrayable
{
}

class Model implements Arrayable
{
}

final class User extends Model
{
}

/**
 * @template TModel of Model
 */
final class Collection
{
    /**
     * @return ($key is Model ? TModel|null : ($key is (Arrayable|array<mixed>) ? static : TModel|null))
     */
    public function find(mixed $key): mixed // T: ($key is Model ? TModel|null : ($key is (Arrayable|array<mixed>) ? static : TModel|null)) // E?[noise]: the body is not under test
    {
        return null;
    }
}

function takesUserOrNull(?User $user): void
{
}

/**
 * @param Collection<User> $collection
 */
function takesCollection(Collection $collection): void // E?[noise]: some tools flag the phpdoc/native generic mismatch
{
}

/**
 * @param Collection<User> $collection
 */
function fromNullableUser(Collection $collection, ?User $user): void // E?[noise]: some tools flag the phpdoc/native generic mismatch
{
    takesUserOrNull($collection->find($user)); // Q: User|null — a Collection arm here is the 2.2.15 leak
}

/**
 * @param Collection<User> $collection
 */
function fromArrayKey(Collection $collection): void // E?[noise]: some tools flag the phpdoc/native generic mismatch
{
    takesCollection($collection->find([1])); // Q?: array key takes the inner Arrayable|array branch
    takesUserOrNull($collection->find([1])); // E?: that branch is Collection, not ?User
}
