<?php

declare(strict_types=1);

namespace Conformance\Tests\AliasesStaticVariables;

/**
 * Local type aliases on static properties.
 *
 * `aliases_local_type` uses an alias as a `@param`. This file asks whether
 * the same expansion applies to a static property `@var` and a static
 * method `@return` — native types are omitted so the alias is the only
 * source of the int/string distinction.
 *
 * Source: vimeo/psalm#10362 (6.0.0).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/10362
 */

/**
 * @phpstan-type UserId int
 * @phpstan-type UserName string
 * @psalm-type UserId = int
 * @psalm-type UserName = string
 */
final class User // T: @phpstan-type / @psalm-type
{
    /**
     * @var UserId
     */
    private static $nextId = 1; // T: UserId

    /**
     * @var UserName
     */
    private static $defaultName = 'guest'; // T: UserName

    /**
     * @return UserId
     */
    public static function getNextId() // T: UserId
    {
        return self::$nextId;
    }

    /**
     * @return UserName
     */
    public static function getDefaultName() // T: UserName
    {
        return self::$defaultName;
    }
}

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

takesInt(User::getNextId()); // V
takesString(User::getNextId()); // E?: UserId is int
takesString(User::getDefaultName()); // V
takesInt(User::getDefaultName()); // E?: UserName is string
