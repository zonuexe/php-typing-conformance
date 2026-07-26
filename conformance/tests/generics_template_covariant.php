<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsTemplateCovariant;

/**
 * Template variance: covariant type parameters.
 *
 * A producer annotated `@template-covariant T` may accept `Producer<Child>`
 * where `Producer<Parent>` is required. Without covariance, many tools reject
 * the same assignment.
 *
 * This case checks the *covariant* direction: Child producer used as Parent
 * producer should be accepted when variance is modeled (no diagnostic).
 *
 * References:
 * - PHPStan: template-covariant / call-site variance
 * - Psalm: templated_annotations / variance
 */

class Animal
{
}

class Dog extends Animal
{
}

/**
 * @template-covariant T
 */
interface Producer
{
    /**
     * @return T
     */
    public function get(): mixed;
}

/**
 * @implements Producer<Dog>
 */
final class DogProducer implements Producer
{
    #[\Override]
    public function get(): Dog
    {
        return new Dog();
    }
}

/**
 * @param Producer<Animal> $producer
 */
function takesAnimalProducer(Producer $producer): void // E<noverify>: NoVerify does not accept generic parameter annotations
{
}

// Dog is a subtype of Animal; covariant T allows Producer<Dog> here.
// A diagnostic here would mean the tool lacks covariant template support
// (or treats Producer as invariant). Optional so non-modeling tools may fail
// silently or warn without failing the suite.
takesAnimalProducer(new DogProducer()); // E?: invariant tools may reject Producer<Dog> as Producer<Animal>
