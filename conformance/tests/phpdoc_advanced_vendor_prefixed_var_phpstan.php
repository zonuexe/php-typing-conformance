<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedVarPhpStan;

/**
 * Cross-tool handling of `@phpstan-var`
 *
 * The prefixed form of `@var`, here on a property with no native type. Reading
 * it is what gives the property a type at all, so a tool that ignores the
 * prefix sees an untyped property rather than a wrongly typed one.
 *
 * References:
 * - PHPStan phpdocs-basics: prefixed @phpstan-var
 * - Psalm supported_annotations.md: @psalm-var
 */

final class Holder
{
    /**
     * @phpstan-var int
     */
    public $value = 1; // T: @phpstan-var
}

function takesString(string $value): void
{
}

// The prefixed tag says the property is an int, which is not a string.
takesString((new Holder())->value); // E?: @phpstan-var int should be enforced where the property is read
