<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedStaticThisReturn;

/**
 * Late-static-bound and exact-instance return annotations.
 *
 * Native `self` is the declaring class. `@return static` / `@return $this`
 * should still be Child when called on a Child, and still be Base when called
 * on a Base. The Child calls are the control: an analyzer that only reads
 * `self` rejects them.
 *
 * References:
 * - PHPStan static and $this types
 * - Psalm static and $this types
 */

class Base
{
    /**
     * @return static
     */
    public function copyAsStatic(): self // T: static
    {
        return $this;
    }

    /**
     * @return $this
     */
    public function copyAsThis(): self // T: $this
    {
        return $this;
    }
}

final class Child extends Base
{
}

function takesChild(Child $value): void
{
}

$child = new Child();
takesChild($child->copyAsStatic()); // V
takesChild($child->copyAsThis()); // V

$base = new Base();
takesChild($base->copyAsStatic()); // E?: static on Base is Base, not Child
takesChild($base->copyAsThis()); // E?: $this on Base is Base, not Child
