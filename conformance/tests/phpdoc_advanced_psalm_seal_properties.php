<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmSealProperties;

/**
 * Cross-tool handling of `@psalm-seal-properties` / `@seal-properties`.
 *
 * With magic `__get`/`__set`, only properties listed in `@property` (and
 * read/write variants) are allowed once the class is sealed.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-seal-properties`
 */

/**
 * @property string $name
 * @psalm-seal-properties
 */
final class SealedBag // T: @psalm-seal-properties
{
    public function __get(string $name): mixed
    {
        return $name === 'name' ? '' : null;
    }

    public function __set(string $name, mixed $value): void
    {
    }
}

$bag = new SealedBag();
$bag->name = 'Ada';

// Not declared in @property, so sealed classes reject it.
$bag->extra = 1; // E?: undeclared magic property under seal-properties
