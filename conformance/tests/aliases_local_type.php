<?php

declare(strict_types=1);

namespace Conformance\Tests\AliasesLocalType;

/**
 * Local type aliases via vendor-prefixed annotations.
 *
 * `@phpstan-type` / `@psalm-type` bind a short name to a complex shape on the
 * declaring class. Analyzers that expand the alias reject a missing required
 * key when the alias is used as a `@param` type.
 *
 * References:
 * - PHPStan phpdoc-types: Local type aliases
 * - Psalm utility_types.md: Type aliases
 */

/**
 * @phpstan-type UserAddress array{street: string, city: string, zip: string}
 * @psalm-type UserAddress = array{street: string, city: string, zip: string}
 */
final class User // T: @phpstan-type / @psalm-type
{
    /**
     * @param UserAddress $address
     */
    public function setAddress($address): void // T: UserAddress
    {
    }
}

$user = new User();

// Valid complete shape — tools that model the alias accept. An analyzer that
// treats the alias as a class name rejects this too (incidental, not enforcement).
$user->setAddress(['street' => '1 Main St', 'city' => 'Town', 'zip' => '12345']); // V

// Missing required key `zip` — alias expansion should reject this shape.
$user->setAddress(['street' => '1 Main St', 'city' => 'Town']); // E?: incomplete UserAddress shape should be rejected
