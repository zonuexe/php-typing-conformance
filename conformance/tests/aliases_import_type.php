<?php

declare(strict_types=1);

namespace Conformance\Tests\AliasesImportType;

/**
 * Importing a local type alias into another class.
 *
 * After `@phpstan-import-type` / `@psalm-import-type`, the imported name is
 * usable in the importing class. Tools that resolve imports reject a value
 * that does not match the original alias; tools that ignore import tags fall
 * back to an open array and accept it.
 *
 * References:
 * - PHPStan phpdoc-types: Local type aliases (import)
 * - Psalm utility_types.md: Type aliases
 */

/**
 * @phpstan-type Coordinates array{lat: float, lng: float}
 * @psalm-type Coordinates = array{lat: float, lng: float}
 */
final class Geo
{
}

/**
 * @phpstan-import-type Coordinates from Geo
 * @psalm-import-type Coordinates from Geo
 */
final class Map
{
    /**
     * @param Coordinates $point // E<noverify>: NoVerify treats the imported alias name as a class
     */
    public function pin($point): void // E<phan>: Phan does not expand imported type aliases
    {
    }
}

$map = new Map();

// Valid complete shape.
$map->pin(['lat' => 35.0, 'lng' => 139.0]); // E<phan>: Phan treats Coordinates as an undeclared class type

// Wrong value type for `lat` — imported alias should still enforce float.
$map->pin(['lat' => 'north', 'lng' => 139.0]); // E?: imported Coordinates should reject string lat // E<phan>: Phan rejects any array against unresolved Coordinates
