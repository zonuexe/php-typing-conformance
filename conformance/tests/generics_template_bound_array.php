<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsTemplateBoundArray;

/**
 * `@template T of array` — a bound that is not a class
 *
 * The existing bound test uses an interface, which a tool can check by looking
 * the name up in its class table. A native type in the same position cannot be
 * resolved that way: `array` is a keyword, and a tool that treats everything
 * after `of` as a class name either invents a class called `array` or drops the
 * bound entirely.
 *
 * References:
 * - PHPStan phpdocs-basics: @template bounds
 * - Psalm templated_annotations.md: template bounds
 */

/**
 * @template T of array
 */
final class Collection
{
    /**
     * @param T $items
     */
    public function __construct(
        public $items,
    ) {
    }
}

/**
 * @param Collection<array{id: int}> $collection
 */
function takesArrayCollection(Collection $collection): void
{
}

/**
 * An array satisfies the bound. The row is passed in rather than written
 * inline, so a tool that infers `array{id: 1}` from a literal does not reject
 * the valid case for being too precise.
 *
 * @param array{id: int} $row
 */
function fillCollection(array $row): void
{
    takesArrayCollection(new Collection($row));
}

// The constructor takes `T $items`, so with T bound to `array` the bound is
// what an argument has to satisfy. Blaming a call site keeps the expectation
// off the docblock/declaration pair the analyzers disagree about.
new Collection(1); // E: 1 is outside the `of array` bound on T
