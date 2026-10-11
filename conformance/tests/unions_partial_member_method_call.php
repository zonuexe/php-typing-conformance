<?php

declare(strict_types=1);

namespace Conformance\Tests\UnionsPartialMemberMethodCall;

/**
 * Method declared on only one union member.
 *
 * Calling a method that only one member of a union declares. On `User|Guest`,
 * `name()` exists only on User, so the call fails at runtime whenever a Guest
 * arrives. PHPStan gates this behind level 7 (`checkUnionTypes`); a lenient
 * tool resolves the call against whichever member has it.
 *
 * References:
 * - PHPStan `checkUnionTypes` (level 7)
 * - unions_object_member_rejection (union argument acceptance)
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }

    public function id(): int
    {
        return 1;
    }
}

final class Guest
{
    public function id(): int
    {
        return 0;
    }
}

function common(User|Guest $visitor): void
{
    echo $visitor->id(); // V: both members declare id()
}

function partial(User|Guest $visitor): void
{
    echo $visitor->name(); // E: Guest has no name()
}
