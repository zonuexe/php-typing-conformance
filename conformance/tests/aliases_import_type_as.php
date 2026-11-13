<?php

declare(strict_types=1);

namespace Conformance\Tests\AliasesImportTypeAs;

/**
 * Importing a local type alias under a new name (`as`).
 *
 * `@phpstan-import-type X from C as Y` / `@psalm-import-type X from C as Y`
 * binds the imported shape to `Y` in the importing class. Tools that import
 * but drop `as` leave `Y` unresolved (often as a class name) and reject the
 * valid array; tools that ignore import entirely do the same.
 *
 * References:
 * - PHPStan phpdoc-types: Local type aliases (optionally rename)
 * - Psalm supported_annotations.md: `@psalm-import-type … as`
 */

/**
 * @phpstan-type Coordinates array{lat: float, lng: float}
 * @psalm-type Coordinates = array{lat: float, lng: float}
 */
final class Geo // T: @phpstan-type / @psalm-type
{
}

/**
 * @phpstan-import-type Coordinates from Geo as Point
 * @psalm-import-type Coordinates from Geo as Point
 */
final class Map // T: @phpstan-import-type / @psalm-import-type
{
    /**
     * @param Point $point
     */
    public function pin($point): void // T: Point
    {
    }
}

$map = new Map();

// Valid complete shape under the renamed alias.
$map->pin(['lat' => 35.0, 'lng' => 139.0]); // V

// Wrong value type for `lat` — the renamed alias should still enforce float.
$map->pin(['lat' => 'north', 'lng' => 139.0]); // E?: imported Point should reject string lat
