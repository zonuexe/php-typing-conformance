<?php

declare(strict_types=1);

/**
 * Basic instanceof-based narrowing checks.
 *
 * References:
 * - common analyzer narrowing on instanceof branches
 */

interface Named
{
    public function name(): string;
}

final class User implements Named
{
    #[\Override]
    public function name(): string
    {
        return 'user';
    }
}

final class Guest
{
    public function guestId(): int
    {
        return 1;
    }
}

/**
 * @param User|Guest $value
 */
function takesUserOrGuest(object $value): void
{
    if ($value instanceof User) {
        echo $value->name();
        return;
    }

    $value->name(); // E: Guest branch should not expose User methods
}
