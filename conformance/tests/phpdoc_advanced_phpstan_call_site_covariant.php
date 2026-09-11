<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanCallSiteCovariant;

/**
 * `Collection<covariant Animal>` (call-site covariance)
 *
 * A PHPStan call-site variance spelling. Attaching `covariant` to a type
 * argument lets a function accept `Collection<Cat>` where `Collection<Animal>`
 * is written, without making the class itself `@template-covariant`. The cost
 * moves to that parameter: `add()` expects `never`, because a write would let
 * a Dog into a collection of Cats.
 *
 * Declaration-site `@template-covariant` is `generics_template_covariant`.
 *
 * References:
 * - PHPStan phpdoc-types: Generics (`Collection<covariant Type>`)
 * - PHPStan "A guide to call-site generic variance"
 */

class Animal
{
}

class Cat extends Animal
{
}

class Dog extends Animal
{
}

/**
 * @template T
 */
final class Collection
{
    /**
     * @param T $item
     */
    public function __construct(
        public mixed $item,
    ) {
    }

    /**
     * @param T $item
     */
    public function add($item): void
    {
        $this->item = $item;
    }

    /**
     * @return T
     */
    public function get(): mixed
    {
        return $this->item;
    }
}

/**
 * @return Collection<Animal>
 */
function animals(): Collection
{
    return new Collection(new Animal());
}

/**
 * @return Collection<Cat>
 */
function cats(): Collection
{
    return new Collection(new Cat());
}

/**
 * @param mixed $item
 * @return Collection<mixed>
 */
function mixeds(mixed $item = null): Collection
{
    return new Collection($item);
}

/**
 * @param Collection<covariant Animal> $animals
 */
function takesCovariant(Collection $animals): void // T: Collection<covariant Animal>
{
}

/**
 * @param Collection<covariant Animal> $animals
 */
function writeCovariant(Collection $animals): void // T: Collection<covariant Animal>
{
    $animals->add(new Dog()); // E?: add() on a covariant projection expects never
}

/**
 * @param Collection<Animal> $animals
 */
function writeInvariant(Collection $animals): void
{
    $animals->add(new Dog()); // V: the same write is valid on invariant Collection<Animal>
}

/**
 * @param Collection<Animal> $animals
 */
function takesInvariant(Collection $animals): void
{
}

takesCovariant(animals()); // V
takesCovariant(cats()); // V: Collection<Cat> satisfies Collection<covariant Animal>
takesCovariant(mixeds()); // E?: mixed is not a subtype of Animal
writeCovariant(cats()); // V
writeInvariant(animals()); // V

// Contrast: without the keyword, Collection is invariant.
takesInvariant(cats()); // E?[noise]: Collection<Cat> is not Collection<Animal>
