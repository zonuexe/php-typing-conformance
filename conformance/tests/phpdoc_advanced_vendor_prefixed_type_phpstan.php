<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedTypePhpStan;

/**
 * Cross-tool handling of `@phpstan-type`
 *
 * A local type alias declared with only this vendor's tag. The existing alias
 * test declares `@phpstan-type` and `@psalm-type` side by side on the same
 * class, which cannot say which of the two a tool actually read; here the other
 * spelling is absent, so an analyzer that only knows Psalm's has nothing to
 * fall back on.
 *
 * References:
 * - PHPStan phpdoc-types: Local type aliases
 * - Psalm utility_types.md: Type aliases
 */

/**
 * @phpstan-type UserRow array{id: int, name: string}
 */
final class Repository // T: @phpstan-type
{
    /**
     * @param UserRow $row
     */
    public function save($row): void // T: @phpstan-type
    {
    }
}

$repository = new Repository();

// A value matching the alias.
$repository->save(['id' => 1, 'name' => 'Ada']); // V

// The alias requires both keys, so this is short one.
$repository->save(['id' => 1]); // E?: the alias requires a `name` key
