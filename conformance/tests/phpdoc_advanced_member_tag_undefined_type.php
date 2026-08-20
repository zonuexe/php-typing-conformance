<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedMemberTagUndefinedType;

/**
 * Class-level member tags name types that must exist.
 *
 * `@mixin`, `@property` and `@method` declare members that have no AST of
 * their own. A typo in those tags is otherwise silent, and an unresolved
 * reference is compatible with anything downstream. The same type written
 * in `@param` or `@return` is caught, so the gap is easy to mistake for
 * the tag being fine.
 *
 * Source: carthage-software/mago#2193
 *
 * References:
 * - PHPStan phpdocs-basics: Mixins, magic properties and methods
 * - Psalm supported_annotations.md: @mixin, @property, @method
 * - https://github.com/carthage-software/mago/pull/2193
 */

final class Delegated
{
    public function answer(): int
    {
        return 42;
    }
}

/** @mixin Delegated */
final class KnownMixin // V
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        return $name === 'answer' ? (new Delegated())->answer() : null;
    }
}

/** @mixin MissingMixin */ // E[mixin]: @mixin names a class that does not exist
final class UnknownMixin // E[mixin]
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        throw new \BadMethodCallException($name);
    }
}

/** @property int $count */
final class KnownProperty // V
{
    public function __get(string $name): mixed
    {
        return $name === 'count' ? 0 : null;
    }
}

/** @property MissingProperty $count */ // E[property]: @property names a class that does not exist
final class UnknownProperty // E[property]
{
    public function __get(string $name): mixed
    {
        return null;
    }
}

/** @method Delegated find(string $id) */
final class KnownMethod // V
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        return $name === 'find' ? new Delegated() : null;
    }
}

/** @method MissingMethod find(string $id) */ // E[method]: @method names a class that does not exist
final class UnknownMethod // E[method]
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        throw new \BadMethodCallException($name);
    }
}
