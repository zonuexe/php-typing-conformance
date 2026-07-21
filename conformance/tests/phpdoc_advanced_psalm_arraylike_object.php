<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmArraylikeObject;

/**
 * `arraylike-object` is a Psalm-only object refinement.
 *
 * `arraylike-object` matches an object implementing ArrayAccess, Countable and
 * Traversable (e.g. ArrayObject). Psalm models it structurally; other analyzers
 * do not recognize the keyword and fall back to accepting any object.
 *
 * References:
 * - Psalm `arraylike-object` structural object refinement
 */

/**
 * @param arraylike-object $obj
 */
function acceptsArrayLike($obj): void
{
}

// ArrayObject implements ArrayAccess, Countable and Traversable.
acceptsArrayLike(new \ArrayObject([1, 2, 3]));

// A plain object is not array-like; analyzers that model it reject this.
acceptsArrayLike(new \stdClass()); // E?: stdClass is not an arraylike-object
