<?php

declare(strict_types=1);

namespace Conformance\Tests\AliasesShapeInlineComments;

/**
 * Inline comments inside a typedef array shape.
 *
 * If a comment in the shape is treated as a parse error, the alias is not
 * a shape and the missing-key probe stays silent. `aliases_local_type` is
 * the same expansion without comments.
 *
 * Source: vimeo/psalm#10623 (5.22.0).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/10623
 */

/**
 * @phpstan-type UserShape array{
 *   id: int, // user ID
 *   name: string, // user name
 * }
 * @psalm-type UserShape = array{
 *   id: int, // user ID
 *   name: string, // user name
 * }
 */
final class User // T: @phpstan-type / @psalm-type
{
    /**
     * @param UserShape $user
     */
    public function take(array $user): void // T: UserShape
    {
    }
}

$user = new User();
$user->take(['id' => 1, 'name' => 'a']); // V
$user->take(['id' => 1]); // E?: name is required
$user->take(['id' => 1, 'name' => 2]); // E?: name is string
