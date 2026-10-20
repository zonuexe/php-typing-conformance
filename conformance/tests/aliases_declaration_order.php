<?php

declare(strict_types=1);

namespace Conformance\Tests\AliasesDeclarationOrder;

/**
 * Nested local type aliases, including reverse declaration order.
 *
 * `aliases_local_type` expands a single alias. This file asks whether an
 * alias may mention another alias, and whether the mentioned name has to
 * appear above the one that uses it. Psalm 6.19.2 started requiring
 * dependency order (vimeo/psalm#12149); tools that resolve names after
 * collecting every `@psalm-type` on the class still expand both blocks.
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/12149
 * - PHPStan phpdoc-types: Local type aliases
 */

/**
 * @phpstan-type UserId int
 * @phpstan-type User array{id: UserId, name: string}
 * @phpstan-type UserList list<User>
 * @psalm-type UserId = int
 * @psalm-type User = array{id: UserId, name: string}
 * @psalm-type UserList = list<User>
 */
final class Forward // T: @phpstan-type / @psalm-type
{
    /**
     * @param UserList $users
     */
    public function takeUsers(array $users): void // T: UserList
    {
    }
}

/**
 * Mentioned names declared after the aliases that use them.
 *
 * @phpstan-type ReverseList list<ReverseUser>
 * @phpstan-type ReverseUser array{id: ReverseId, name: string}
 * @phpstan-type ReverseId int
 * @psalm-type ReverseList = list<ReverseUser>
 * @psalm-type ReverseUser = array{id: ReverseId, name: string}
 * @psalm-type ReverseId = int
 */
final class Reverse // T: @phpstan-type / @psalm-type
{
    /**
     * @param ReverseList $users
     */
    public function takeUsers(array $users): void // T: ReverseList
    {
    }
}

$forward = new Forward();
$forward->takeUsers([['id' => 1, 'name' => 'a']]); // V
$forward->takeUsers([['id' => 'x', 'name' => 'a']]); // E?: UserId is int, so id must be int

$reverse = new Reverse();
$reverse->takeUsers([['id' => 1, 'name' => 'a']]); // V
$reverse->takeUsers([['id' => 'x', 'name' => 'a']]); // E?: ReverseId is still int when aliases are collected before expansion
