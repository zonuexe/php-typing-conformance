<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsThisOutSelfOut;

/**
 * Post-call narrowing via self-out annotations.
 *
 * References:
 * - Psalm `@psalm-this-out`
 * - PHPStan `@phpstan-self-out`
 * - Mago removed `@this-out` support as unsound
 */

/**
 * @template T
 */
final class MutableBox
{
    /** @param T $value */
    public function __construct(
        public mixed $value, // E<noverify>: NoVerify does not reconcile the promoted property with the template-bound constructor param
    ) {
    }

    /**
     * @template U
     * @param U $value // E<noverify>: NoVerify does not understand method-level template parameters here
     * @psalm-this-out self<U>
     * @phpstan-self-out self<U>
     */
    public function replace(mixed $value): void // E<phan>: Phan requires method template parameters to appear in the return type // E<noverify>: NoVerify treats the method template param as an unresolved class name
    {
    }
}

/** @param MutableBox<int> $box */
function takesIntBox(MutableBox $box): void // E<noverify>: NoVerify does not accept this generic parameter annotation on the function parameter
{
}

/** @param MutableBox<string> $box */
function takesStringBox(MutableBox $box): void // E<noverify>: NoVerify does not accept this generic parameter annotation on the function parameter
{
}

function inspectReplacement(string $next): void
{
    $box = new MutableBox(1);

    takesIntBox($box);
    takesStringBox($box); // E<phpstan>: int box should not satisfy string box before replacement // E<phpstan-strict>: int box should not satisfy string box before replacement // E<psalm>: int box should not satisfy string box before replacement // E<mago>: int box should not satisfy string box before replacement // E<phan>: int box should not satisfy string box before replacement

    $box->replace($next);

    takesStringBox($box); // E<mago>: Mago intentionally does not honor self-out / this-out // E<phan>: Phan does not support self-out / this-out template updates
    takesIntBox($box); // E<phpstan>: replacement should update MutableBox<int> to MutableBox<string> // E<phpstan-strict>: replacement should update MutableBox<int> to MutableBox<string> // E<psalm>: replacement should update MutableBox<int> to MutableBox<string>
}
