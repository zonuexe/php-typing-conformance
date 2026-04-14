<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsNullGuard;

/**
 * Null-check narrowing, analogous to Python narrowing tests.
 *
 * References:
 * - common analyzer narrowing on null checks
 * - python-typing narrowing groups
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

function takesMaybeUser(?User $user): void
{
    if ($user === null) {
        return;
    }

    echo $user->name();
}

function usesNullBranch(?User $user): void
{
    if ($user !== null) {
        return;
    }

    $user->name(); // E: null branch should not expose User methods
}
