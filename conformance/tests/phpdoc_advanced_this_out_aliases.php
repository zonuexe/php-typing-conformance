<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedThisOutAliases;

/**
 * Alias spellings of post-call receiver updates.
 *
 * `assertions_this_out_self_out` measures the canonical pair
 * `@psalm-this-out` / `@phpstan-self-out`. Psalm and PHPStan each document
 * the swapped names as aliases (`@psalm-self-out`, `@phpstan-this-out`).
 *
 * References:
 * - Psalm adding_assertions.md: `@psalm-this-out` aliases `@psalm-self-out` / `@phpstan-self-out` / `@phpstan-this-out`
 * - PHPStan phpdocs-basics: `@phpstan-self-out` or `@phpstan-this-out`
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
     * @psalm-self-out self<U>
     * @phpstan-this-out self<U>
     */
    public function replace(mixed $value): void // T: @psalm-self-out / @phpstan-this-out // E<noverify>: NoVerify treats the method template param as an unresolved class name
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

    takesIntBox($box); // V
    takesStringBox($box); // E?: int box should not satisfy string box before replacement

    $box->replace($next);

    // Honouring either alias types the receiver as MutableBox<string>.
    // Tools that ignore both aliases still see MutableBox<int> here.
    takesStringBox($box); // E?[noise]
    takesIntBox($box); // E?: replacement should update MutableBox<int> to MutableBox<string>
}
