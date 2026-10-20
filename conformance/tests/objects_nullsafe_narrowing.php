<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsNullsafeNarrowing;

/**
 * A successful nullsafe access narrows the receiver to non-null.
 *
 * After `if ($user?->name !== null)`, `$user` is `User`. The unguarded
 * `$user->name` is the control: a diagnostic there is about nullability in
 * general, not about the nullsafe form. `$user = new User()` would make
 * both sides silent.
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
        takesString($user->name); // V: the receiver is non-null
    }
}

function unguarded(?User $user): void
{
    takesString($user->name); // E?: ?User is not User without a guard
}
