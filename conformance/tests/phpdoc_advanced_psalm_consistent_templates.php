<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmConsistentTemplates;

/**
 * Cross-tool handling of `@psalm-consistent-templates`.
 *
 * Children of a generic class must keep the same template parameters, so
 * `new static($u)` returning `static<U>` stays sound. A child that pins the
 * parent's `T` to a concrete type is the documented violation.
 *
 * `@psalm-consistent-constructor` is required by the documented example so
 * `new static` is constructor-safe; this file measures the templates tag.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-consistent-templates`
 * - Psalm UnsafeGenericInstantiation
 */

/**
 * @template T
 * @psalm-consistent-constructor
 * @psalm-consistent-templates
 */
class Container // T: @psalm-consistent-templates
{
    /** @var T */
    public $value;

    /**
     * @param T $value // E?[noise]
     */
    public function __construct($value) // E?[noise]
    {
        $this->value = $value;
    }

    /**
     * @template U
     * @param U $value // E?[noise]
     * @return static<U>
     */
    public function getInstance($value): static // E?[noise]
    {
        return new static($value);
    }
}

/**
 * @template T
 * @extends Container<T>
 */
class MatchingChild extends Container // V
{
}

/**
 * @extends Container<string>
 */
class PinnedChild extends Container // E?: child must keep the same generic params as Container
{
}

$matching = new MatchingChild(1); // V
echo $matching->getInstance('x')->value;

$pinned = new PinnedChild('ok');
echo $pinned->getInstance(new \stdClass())->value; // E?: new static under pinned T is unsound
