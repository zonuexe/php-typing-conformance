<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanCallSiteContravariant;

/**
 * `Collection<contravariant Dog>` (call-site contravariance)
 *
 * A PHPStan call-site variance spelling. Attaching `contravariant` to a type
 * argument lets a function accept `Collection<Animal>` (or `Collection<mixed>`)
 * where `Collection<Dog>` is written. Writes of `Dog` stay valid; reads are
 * `mixed`, because the item type is not bounded from above.
 *
 * Declaration-site `@template-contravariant` is `generics_template_contravariant`.
 *
 * References:
 * - PHPStan phpdoc-types: Generics (`Collection<contravariant Type>`)
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
 * @return Collection<Dog>
 */
function dogs(): Collection
{
    return new Collection(new Dog());
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

function takesDog(Dog $dog): void
{
}

/**
 * @param Collection<contravariant Dog> $collection
 */
function takesContravariant(Collection $collection): void // T: Collection<contravariant Dog>
{
}

/**
 * @param Collection<contravariant Dog> $collection
 */
function writeContravariant(Collection $collection): void // T: Collection<contravariant Dog>
{
    $collection->add(new Dog()); // V: writes of Dog are allowed
}

/**
 * @param Collection<contravariant Dog> $collection
 */
function readContravariant(Collection $collection): void // T: Collection<contravariant Dog>
{
    takesDog($collection->get()); // E?: get() on a contravariant projection is mixed
}

/**
 * @param Collection<Dog> $collection
 */
function takesInvariant(Collection $collection): void
{
}

/**
 * @param Collection<Dog> $collection
 */
function readInvariant(Collection $collection): void
{
    takesDog($collection->get()); // V: get() on Collection<Dog> is Dog
}

takesContravariant(dogs()); // V
takesContravariant(animals()); // V: Collection<Animal> satisfies Collection<contravariant Dog>
takesContravariant(mixeds()); // V: Collection<mixed> too
takesContravariant(cats()); // E?: Cat is not a supertype of Dog
writeContravariant(animals()); // V
readContravariant(dogs()); // V
readInvariant(dogs()); // V

// Contrast: without the keyword, Collection is invariant.
takesInvariant(animals()); // E?[noise]: Collection<Animal> is not Collection<Dog>
