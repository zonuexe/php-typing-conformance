<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsPropertyVariance;

/**
 * `@property` types are invariant under inheritance.
 *
 * A child that widens or narrows a magic property relative to its parent
 * is a variance error in the docblock. Native typed properties are already
 * invariant at compile time; this file asks whether the same check applies
 * to `@property`.
 *
 * Source: vimeo/psalm#5131 (4.5.0).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/5131
 */

interface ParentInterface
{
}

interface ChildInterface extends ParentInterface
{
}

/**
 * @property ChildInterface $property
 */
class ParentClass
{
    public function __get(string $name): mixed
    {
        return null;
    }
}

/** @property ParentInterface $property */ // E?[variance]: @property ParentInterface is not invariant with ChildInterface
class ChildClass extends ParentClass // E?[variance]
{
}

/** @property ChildInterface $property */
class MatchingChild extends ParentClass // V: the same @property type is invariant
{
}
