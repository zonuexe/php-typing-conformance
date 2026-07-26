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
final class User
{
    /**
     * @param UserAddress $address // E<noverify>: NoVerify treats the alias name as a class
     */
    public function setAddress($address): void // E<phan>: Phan does not expand @phpstan-type / @psalm-type aliases
    {
    }
}

$user = new User();

// Valid complete shape — tools that model the alias accept; Phan rejects arrays
// against the unresolved alias name.
$user->setAddress(['street' => '1 Main St', 'city' => 'Town', 'zip' => '12345']); // E<phan>: Phan treats UserAddress as an undeclared class type

// Missing required key `zip` — alias expansion should reject this shape.
$user->setAddress(['street' => '1 Main St', 'city' => 'Town']); // E?: incomplete UserAddress shape should be rejected // E<phan>: Phan rejects any array against unresolved UserAddress
