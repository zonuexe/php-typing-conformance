<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedTypePsalm;

/**
 * Cross-tool handling of `@psalm-type`
 *
 * The same alias under the other vendor's tag, and with the other vendor's
 * syntax: Psalm writes an `=` between the name and the type where PHPStan
 * writes nothing. A tool that reads the tag but not the `=` is a real
 * possibility, so this is two questions in one spelling.
 *
 * References:
 * - Psalm utility_types.md: Type aliases
 * - PHPStan phpdoc-types: Local type aliases
 */

/**
 * @psalm-type UserRow = array{id: int, name: string}
 */
final class Repository // T: @psalm-type
{
    /**
     * @param UserRow $row
     */
    public function save($row): void // T: @psalm-type
    {
    }
}

$repository = new Repository();

// A value matching the alias.
$repository->save(['id' => 1, 'name' => 'Ada']);

// The alias requires both keys, so this is short one.
$repository->save(['id' => 1]); // E?: the alias requires a `name` key
