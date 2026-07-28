<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsTemplateContravariant;

/**
 * Template variance: contravariant type parameters
 *
 * The counterpart of the covariant case. A consumer annotated
 * `@template-contravariant T` may accept `Consumer<Animal>` where
 * `Consumer<Dog>` is required, because anything that can handle every animal
 * can handle a dog. Without variance the substitution is rejected.
 *
 * This case checks the *contravariant* direction: a parent consumer used where
 * a child consumer is required should be accepted when variance is modeled.
 *
 * The spelling is not universal: PHPStan reserves `@template-contravariant`
 * alongside `@template-covariant`, while Psalm's documentation only ever names
 * the covariant one. A tool that does not know the tag does not merely lose the
 * variance — it loses the template parameter, and then says the class takes
 * none.
 *
 * References:
 * - PHPStan phpdocs-basics: @template, @template-covariant, @template-contravariant
 * - Psalm templated_annotations.md: template variance
 */

class Animal
{
}

class Dog extends Animal
{
}

/**
 * @template-contravariant T
 */
final class Consumer
{
    /**
     * @param T $value
     */
    public function consume($value): void
    {
    }
}

/**
 * @param Consumer<Dog> $consumer
 */
function takesDogConsumer(Consumer $consumer): void
{
}

/**
 * @return Consumer<Animal>
 */
function animalConsumer(): Consumer
{
    return new Consumer();
}

// Contravariance: a consumer of any Animal can stand in for a consumer of Dog.
takesDogConsumer(animalConsumer());
