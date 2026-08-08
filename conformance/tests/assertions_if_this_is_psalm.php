<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsIfThisIsPsalm;

/**
 * Precondition on the receiver via `@psalm-if-this-is`.
 *
 * Companion to `@psalm-this-out`: after a template update that leaves
 * `Box<string>`, a method that requires `Box<int>` must be rejected.
 *
 * References:
 * - Psalm adding_assertions.md: `@psalm-if-this-is` / `@psalm-this-out`
 */

/**
 * @template T
 */
final class Box
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
    public function set(mixed $value): void // E<phan>: Phan requires method template parameters to appear in the return type // E<noverify>: NoVerify treats the method template param as an unresolved class name
    {
        // Declarative this-out only; body assignment is out of scope here.
    }

    /**
     * @psalm-if-this-is Box<int>
     */
    public function needsInt(): void // T: @psalm-if-this-is
    {
    }
}

function example(): void
{
    $box = new Box(1);
    $box->needsInt();

    $box->set('x');
    $box->needsInt(); // E?: after this-out to Box<string>, if-this-is Box<int> must fail
}
