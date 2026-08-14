<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmPublicPropertiesOf;

/**
 * `public-properties-of<T>` is a Psalm visibility-filtered utility type.
 *
 * Sibling of `properties-of<T>` that only expands *public* non-static
 * properties. Psalm also documents `protected-properties-of` and
 * `private-properties-of`; this file probes the public variant.
 *
 * References:
 * - Psalm utility_types.md: properties-of variants
 */

final class User
{
    public string $name = '';

    protected int $age = 0;

    private string $secret = '';

    public function describe(): string
    {
        // Touch non-public fields so unused-property rules stay quiet.
        return $this->name . (string) $this->age . $this->secret;
    }
}

/**
 * @param public-properties-of<User> $data
 */
function acceptsPublicUserProps(array $data): void // T: public-properties-of<User>
{
}

// Only the public property is required / accepted by the shape.
acceptsPublicUserProps(['name' => 'Ada']); // V

// A non-public property key is not part of public-properties-of<User>.
acceptsPublicUserProps(['name' => 'Ada', 'age' => 42]); // E?: age is protected, not in public-properties-of

// Wrong type on the public property.
acceptsPublicUserProps(['name' => 1]); // E?: name must be string in public-properties-of<User>
