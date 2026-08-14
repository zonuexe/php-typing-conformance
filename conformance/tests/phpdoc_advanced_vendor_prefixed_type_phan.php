<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedTypePhan;

/**
 * Cross-tool handling of `@phan-type`.
 *
 * Phan's local type-alias tag, parallel to `@psalm-type` / `@phpstan-type`.
 * Syntax matches Psalm (`Name = Type`). A tool that only reads the other
 * vendors' prefixes will leave the alias unresolved.
 *
 * References:
 * - Phan Annotating-Your-Source-Code-V6.md: `@phan-type`
 * - Psalm / PHPStan local type aliases
 */

/**
 * @phan-type UserRow = array{id: int, name: string}
 */
final class Repository // T: @phan-type
{
    /**
     * @param UserRow $row
     */
    public function save($row): void // T: @phan-type
    {
    }
}

$repository = new Repository();

// A value matching the alias.
$repository->save(['id' => 1, 'name' => 'Ada']); // V

// The alias requires both keys, so this is short one.
$repository->save(['id' => 1]); // E?: the alias requires a `name` key
