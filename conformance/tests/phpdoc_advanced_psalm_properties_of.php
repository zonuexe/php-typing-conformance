<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmPropertiesOf;

/**
 * `properties-of<T>` is a Psalm-only utility type.
 *
 * `properties-of<T>` expands to an array shape of the class's properties
 * (`array{name: string, age: int}` here). Psalm models it; other analyzers do
 * not recognize the keyword and either ignore it or report an unknown type.
 *
 * References:
 * - Psalm TPropertiesOf (properties-of / public-properties-of ...)
 */

final class User
{
    public string $name = '';

    public int $age = 0;
}

/**
 * @param properties-of<User> $data
 */
function acceptsUserProps(array $data): void // T: properties-of<User>
{
}

// A shape matching the class properties satisfies the parameter.
acceptsUserProps(['name' => 'Ada', 'age' => 36]); // V

// A shape with a wrong property type is rejected by analyzers that model it.
acceptsUserProps(['name' => 'Ada', 'age' => 'old']); // E?: age must be int in properties-of<User>
