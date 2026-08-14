<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmConsistentConstructor;

/**
 * Cross-tool handling of `@psalm-consistent-constructor`.
 *
 * Psalm's spelling of the same idea as `@phpstan-consistent-constructor`:
 * children must keep a compatible constructor so `new static()` is safe.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-consistent-constructor`
 * - PHPStan phpdocs-basics: Consistent constructor
 */

/**
 * @psalm-consistent-constructor
 */
class ParentWithCtor // T: @psalm-consistent-constructor
{
    public function __construct(string $name)
    {
        if ($name === '') {
            throw new \InvalidArgumentException('empty name');
        }
    }
}

// Compatible: inherits the parent's constructor, so the tag must stay silent.
final class ChildWithInheritedCtor extends ParentWithCtor // V
{
}

final class ChildWithDifferentCtor extends ParentWithCtor
{
    public function __construct(int $id) // E?: not compatible under @psalm-consistent-constructor
    {
        if ($id < 0) {
            throw new \InvalidArgumentException('negative id');
        }
    }
}
