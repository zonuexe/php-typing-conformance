<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmSealMethods;

/**
 * Cross-tool handling of `@psalm-seal-methods` / `@seal-methods`.
 *
 * With magic `__call`, only methods listed in `@method` are allowed once the
 * class is sealed.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-seal-methods`
 */

/**
 * @method string greet()
 * @psalm-seal-methods
 */
final class Greeter // T: @psalm-seal-methods
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'greet') {
            return 'hello';
        }

        throw new \BadMethodCallException($name);
    }
}

$greeter = new Greeter();
echo $greeter->greet();

// Not declared in @method, so sealed classes reject it.
echo $greeter->wave(); // E?: undeclared magic method under seal-methods
