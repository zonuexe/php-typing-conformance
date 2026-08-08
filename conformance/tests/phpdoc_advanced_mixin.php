<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedMixin;

/**
 * Cross-tool handling of `@mixin`.
 *
 * The tag tells analyzers that unknown method/property access is delegated to
 * another type. Honouring it means `doA()` on `B` is treated as `A::doA()`.
 *
 * References:
 * - PHPStan phpdocs-basics: Mixins
 * - Psalm supported_annotations.md: @mixins
 */

final class Delegated
{
    public function answer(): int
    {
        return 42;
    }
}

/**
 * @mixin Delegated
 */
final class Facade // T: @mixin
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($name === 'answer') {
            return (new Delegated())->answer();
        }

        throw new \BadMethodCallException($name);
    }
}

function takesInt(int $value): void
{
}

// Mixin makes answer() visible and typed as int.
takesInt((new Facade())->answer());

// A method neither on Facade nor on the mixin target stays undefined.
takesInt((new Facade())->missing()); // E?: missing is not provided by @mixin Delegated
