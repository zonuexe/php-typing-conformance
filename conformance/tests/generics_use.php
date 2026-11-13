<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsUse;

/**
 * Generic trait import via `@use`.
 *
 * A class that documents `@use BoxTrait<int>` on the `use` statement should
 * behave as if the trait were specialised to `int`. `@extends` /
 * `@implements` are measured in `generics_extends_implements`.
 *
 * References:
 * - PHPStan phpdoc-types: `@use` on generic traits
 * - Psalm templated_annotations: `@use` / `@template-use`
 * - Phan Generic-Types: `@use`
 */

/**
 * @template T
 */
trait BoxTrait
{
    /**
     * @param T $value
     * @return T // E?[noise]
     */
    public function wrap($value) // E?[noise]
    {
        return $value; // E?[noise]
    }
}

final class IntHolder
{
    /**
     * @use BoxTrait<int>
     */
    use BoxTrait; // T: @use
}

$holder = new IntHolder();
$holder->wrap(1); // V

// IntHolder is BoxTrait<int>, not BoxTrait<string>.
$holder->wrap('x'); // E?: @use BoxTrait<int> should reject string
