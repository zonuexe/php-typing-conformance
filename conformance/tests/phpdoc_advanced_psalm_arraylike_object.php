<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmArraylikeObject;

/**
 * `arraylike-object<string, int>` is a Psalm-only object refinement.
 *
 * It matches an object implementing ArrayAccess, Countable and Traversable
 * (e.g. ArrayObject). Psalm models it structurally — ArrayAccess makes the
 * element access `$obj[$key]` yield the value type; other analyzers do not
 * recognize the keyword and fall back to accepting any object. The spelling
 * only resolves in generic form — Psalm's bare `arraylike-object` is an
 * unresolvable type, so the key and value type arguments are part of the
 * spelling under test.
 *
 * References:
 * - Psalm `arraylike-object` structural object refinement
 */

/**
 * @param arraylike-object<string, int> $obj
 */
function acceptsArrayLike($obj): void // T: arraylike-object<string, int>
{
}

// ArrayObject implements ArrayAccess, Countable and Traversable.
acceptsArrayLike(new \ArrayObject(['a' => 1]));

// A plain object is not array-like; analyzers that model it reject this.
acceptsArrayLike(new \stdClass()); // E?: stdClass is not an arraylike-object

function takesInt(int $value): void
{
}

/**
 * @param arraylike-object<string, int> $obj
 */
function readsElement($obj): void // T: arraylike-object<string, int>
{
    // The ArrayAccess contract makes element access legal and the value
    // typed: $obj['key'] is int, so `?? null` narrows to int|null. A tool
    // that fell back to object or mixed reports the offset access here.
    $value = $obj['key'] ?? null; // Q: element access is legal via the ArrayAccess contract
    takesInt($value); // E?: the element access yields int|null, so the null branch is not int
}

readsElement(new \ArrayObject(['a' => 1]));
