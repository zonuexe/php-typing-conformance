<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsClosureParamFromMethodTemplate;

/**
 * Closure parameter and return bound through method templates.
 *
 * A closure passed to a method of a generic class takes its parameter type
 * from the class template, and a method-level template is bound from the
 * closure's return: `Collection<User>::map(fn ($u) => new Order())` is a
 * `Collection<Order>`.
 *
 * References:
 * - PHPStan generics by example (callable template binding)
 * - Psalm templated callables
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

final class Order
{
    public function __construct(public string $customer)
    {
    }

    public function total(): int
    {
        return 1;
    }
}

/** @template T */
final class Collection
{
    /** @param list<T> $items */
    public function __construct(private array $items)
    {
    }

    /** @param callable(T): void $callback */
    public function each(callable $callback): void
    {
        foreach ($this->items as $item) {
            $callback($item);
        }
    }

    /**
     * @template U
     * @param callable(T): U $callback
     * @return Collection<U>
     */
    public function map(callable $callback): Collection
    {
        return new Collection(array_map($callback, $this->items));
    }

    /** @return T */
    public function first(): mixed
    {
        return $this->items[0];
    }
}

/** @param Collection<User> $users */
function closureParameter(Collection $users): void
{
    $users->each(function ($user): void {
        echo $user->name(); // V
    });
    $users->each(function ($user): void {
        echo $user->missing(); // E: the closure parameter is a User
    });
}

/** @param Collection<User> $users */
function closureReturn(Collection $users): void
{
    $orders = $users->map(fn ($user) => new Order($user->name()));
    echo $orders->first()->total(); // V
    $mapped = $users->map(fn ($user) => new Order($user->name()));
    echo $mapped->first()->name(); // E: U is bound to Order, not User
}
