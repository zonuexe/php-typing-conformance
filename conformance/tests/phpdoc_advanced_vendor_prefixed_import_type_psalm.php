<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedImportTypePsalm;

/**
 * Cross-tool handling of `@psalm-import-type`
 *
 * The cross-class alias import under the other vendor's prefix, exported by a
 * `@psalm-type` declaration written in Psalm's `=` syntax. Both halves have to
 * be read for the import to resolve, so this records the pair as one dialect.
 *
 * References:
 * - Psalm utility_types.md: Importing type aliases
 * - PHPStan phpdoc-types: Local type aliases (importing)
 */

/**
 * @psalm-type Coordinates = array{lat: float, lng: float}
 */
final class Geo // T: @psalm-type
{
}

/**
 * @psalm-import-type Coordinates from Geo
 */
final class Map // T: @psalm-import-type
{
    /**
     * @param Coordinates $point
     */
    public function pin($point): void // T: @psalm-import-type
    {
    }
}

$map = new Map();

// A value matching the imported alias.
$map->pin(['lat' => 1.0, 'lng' => 2.0]); // V

// The imported alias requires both keys.
$map->pin(['lat' => 1.0]); // E?: the imported alias requires a `lng` key
