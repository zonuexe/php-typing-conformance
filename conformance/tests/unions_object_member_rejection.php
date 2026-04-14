<?php

declare(strict_types=1);

namespace Conformance\Tests\UnionsObjectMemberRejection;

/**
 * Union member rejection for object types.
 *
 * References:
 * - PHP native unions
 * - python-typing union narrowing inspiration
 */

final class User
{
}

final class Guest
{
}

final class Robot
{
}

function takesUserOrGuest(User|Guest $value): void
{
}

takesUserOrGuest(new User());
takesUserOrGuest(new Robot()); // E: Robot is not accepted by User|Guest
