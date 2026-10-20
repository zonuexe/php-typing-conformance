<?php

declare(strict_types=1);

namespace Conformance\Tests\PropertiesDocblockTypeInvariance;

/**
 * Docblock-typed properties are invariant under inheritance.
 *
 * PHP checks invariance only for native property types. An untyped property
 * whose type lives in `@var` gets no runtime check, so a child that widens or
 * narrows it breaks code written against the parent: widening lets a read
 * return a value the parent never promised, narrowing lets a write through
 * the parent store a value the child does not admit.
 *
 * Source: vimeo/psalm#5131 (4.5.0), NonInvariantDocblockPropertyType.
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/5131
 * - Psalm docs: running_psalm/issues/NonInvariantDocblockPropertyType.md
 */

interface ParentInterface
{
}

interface ChildInterface extends ParentInterface
{
}

class Base
{
    /** @var ChildInterface|null */
    public $widened = null;

    /** @var ParentInterface|null */
    public $narrowed = null;

    /** @var ChildInterface|null */
    public $matching = null;
}

final class Child extends Base
{
    /** @var ParentInterface|null */ // E[widened]: widening the parent's ChildInterface is unsound for reads
    public $widened = null; // E[widened]

    /** @var ChildInterface|null */ // E[narrowed]: narrowing the parent's ParentInterface is unsound for writes
    public $narrowed = null; // E[narrowed]

    /** @var ChildInterface|null */
    public $matching = null; // V: the same docblock type is invariant
}
