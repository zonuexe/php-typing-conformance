<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysKeyedDestructuring;

/**
 * Keyed destructuring from an array shape.
 *
 * Keyed destructuring, `['user' => $user] = $shape`, takes each target's type
 * from the matching key of the shape. The positional form is covered by
 * arrays_list_shape_destructuring; this is the string-keyed counterpart.
 *
 * References:
 * - https://wiki.php.net/rfc/list_keys
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
    public function total(): int
    {
        return 1;
    }
}

/** @return array{user: User, order: Order} */
function load(): array
{
    return ['user' => new User(), 'order' => new Order()];
}

function destructures(): void
{
    ['user' => $user, 'order' => $order] = load();
    echo $user->name(); // V
    echo $order->total(); // V

    ['user' => $customer] = load();
    echo $customer->total(); // E: the user key holds a User
    ['order' => $purchase] = load();
    echo $purchase->name(); // E: the order key holds an Order
}

function destructuresLiteral(): void
{
    ['user' => $literal] = ['user' => new User()];
    echo $literal->missing(); // E: the literal's user key holds a User
}
