<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanConsistentConstructor;

/**
 * Cross-tool handling of `@phpstan-consistent-constructor`.
 *
 * Without the tag, PHP allows subclasses to change the constructor signature.
 * With the tag, children must stay compatible so `new static()` is safe.
 *
 * References:
 * - PHPStan phpdocs-basics: Consistent constructor
 */

/**
 * @phpstan-consistent-constructor
 */
class ParentWithCtor // T: @phpstan-consistent-constructor
{
    public function __construct(string $name)
    {
        // Use the parameter so unused-parameter rules stay quiet.
        if ($name === '') {
            throw new \InvalidArgumentException('empty name');
        }
    }
}

// Divergent constructor: rejected only when the parent requires consistency.
final class ChildWithDifferentCtor extends ParentWithCtor
{
    public function __construct(int $id) // E?: not compatible with ParentWithCtor::__construct under @phpstan-consistent-constructor
    {
    }
}
