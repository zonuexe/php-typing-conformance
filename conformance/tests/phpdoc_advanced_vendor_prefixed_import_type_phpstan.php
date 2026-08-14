<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedImportTypePhpStan;

/**
 * Cross-tool handling of `@phpstan-import-type`
 *
 * An alias declared on one class and used on another. Resolving it means
 * carrying a name across a class boundary, which is a step beyond expanding an
 * alias where it was written — a tool can support `@phpstan-type` and still not
 * support this.
 *
 * References:
 * - PHPStan phpdoc-types: Local type aliases (importing)
 * - Psalm utility_types.md: Importing type aliases
 */

/**
 * @phpstan-type Coordinates array{lat: float, lng: float}
 */
final class Geo // T: @phpstan-type
{
}

/**
 * @phpstan-import-type Coordinates from Geo
 */
final class Map // T: @phpstan-import-type
{
    /**
     * @param Coordinates $point
     */
    public function pin($point): void // T: @phpstan-import-type
    {
    }
}

$map = new Map();

// A value matching the imported alias.
$map->pin(['lat' => 1.0, 'lng' => 2.0]); // V

// The imported alias requires both keys.
$map->pin(['lat' => 1.0]); // E?: the imported alias requires a `lng` key
