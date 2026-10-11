<?php

declare(strict_types=1);

namespace Conformance\Tests\IntersectionsInstanceofUnknownMethod;

/**
 * Unknown method on an `instanceof` intersection.
 *
 * `$base instanceof SomeInterface` on a non-final class narrows to the
 * intersection `Base&SomeInterface`. Members of both sides are available;
 * a member of neither is still an error. A tool that gives up on the
 * intersection and goes silent passes the controls but misses the probe.
 *
 * NamedAccount makes the intersection inhabited, so a closed-world tool that
 * enumerates subclasses still sees a reachable branch.
 *
 * References:
 * - intersections_interface_merge (declared intersections)
 */

interface Named
{
    public function name(): string;
}

class Account
{
    public function id(): int
    {
        return 1;
    }
}

final class NamedAccount extends Account implements Named
{
    #[\Override]
    public function name(): string
    {
        return 'named';
    }
}

function narrows(Account $account): void
{
    if ($account instanceof Named) {
        echo $account->name(); // V: from the interface
        echo $account->id(); // V: from the class
    }
}

function narrowsMissing(Account $account): void
{
    if ($account instanceof Named) {
        echo $account->missing(); // E: neither side declares missing()
    }
}
