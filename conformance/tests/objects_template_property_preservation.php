<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsTemplatePropertyPreservation;

/**
 * `@var Box<int>` on a property whose native type is `Box` keeps `T`.
 *
 * Native `Box` has no type argument, so a tool that lets the native type
 * win types `$container->box->value` as `mixed`. `generics_class_string_property_new`
 * is the same question for `class-string<T>`.
 *
 * Source: phan/phan#5556 (6.0.8).
 *
 * References:
 * - https://github.com/phan/phan/pull/5556
 */

/**
 * @template T
 */
final class Box
{
    /**
     * @param T $value
     */
    public function __construct(
        public mixed $value,
    ) {
    }
}

final class Container
{
    /**
     * @var Box<int>
     */
    public Box $box; // T: Box<int>

    public function __construct()
    {
        $this->box = new Box(42);
    }
}

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

$container = new Container();
takesInt($container->box->value); // V: T is int
takesString($container->box->value); // E?: T is int, not string
