<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedVarPsalm;

/**
 * Cross-tool handling of `@psalm-var`
 *
 * The prefixed `@var` under the other vendor, on a property with no native
 * type. Paired with the `@phpstan-` file it says which prefixes a tool reads on
 * a property rather than on a signature.
 *
 * References:
 * - Psalm supported_annotations.md: @psalm-var
 * - PHPStan phpdocs-basics: prefixed @phpstan-var
 */

final class Holder
{
    /**
     * @psalm-var int
     */
    public $value = 1; // T: @psalm-var
}

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

takesInt((new Holder())->value); // V

// The prefixed tag says the property is an int, which is not a string.
takesString((new Holder())->value); // E?: @psalm-var int should be enforced where the property is read
