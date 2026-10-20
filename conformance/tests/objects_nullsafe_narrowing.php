<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsNullsafeNarrowing;

/**
 * A successful nullsafe access narrows the receiver to non-null.
 *
 * After `if ($user?->name !== null)`, `$user` is `User`, so honour is
 * silence on the guarded read. The unguarded `$user->name` is the reference
 * probe: it shows the tool reports a nullable receiver at all, without which
 * silence on the guarded read would prove nothing. `$user = new User()`
 * would make both sides silent.
 *
 * Source: phan/phan#5569 (6.0.8).
 *
 * References:
 * - https://github.com/phan/phan/pull/5569
 */

final class User
{
    public function __construct(
        public string $name,
    ) {
    }
}

function takesString(string $value): void
{
}

function afterNullsafe(?User $user): void
{
    if ($user?->name !== null) {
        takesString($user->name); // Q: the receiver is non-null
    }
}

function unguarded(?User $user): void
{
    takesString($user->name); // E: ?User is not User without a guard
}
