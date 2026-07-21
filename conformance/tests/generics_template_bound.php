<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsTemplateBound;

/**
 * Template upper bounds.
 *
 * References:
 * - PHPStan template bounds
 * - Psalm template bounds
 * - python-typing generics upper bounds inspiration
 */

interface HasName
{
    public function name(): string;
}

final class User implements HasName
{
    #[\Override]
    public function name(): string
    {
        return 'user';
    }
}

final class AnonymousUser
{
}

/**
 * @template T of HasName
 */
final class NamedBox
{
    /**
     * @param T $value
     */
    public function __construct(
        public object $value,
    ) {
    }
}

/**
 * @param NamedBox<User> $box
 */
function takesNamedBox(NamedBox $box): void
{
}

takesNamedBox(new NamedBox(new User()));
takesNamedBox(new NamedBox(new AnonymousUser())); // E: template bound requires HasName-compatible values
