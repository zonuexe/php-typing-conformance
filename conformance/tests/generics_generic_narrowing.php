<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsGenericNarrowing;

/**
 * `instanceof` on a generic intersection keeps the type argument.
 *
 * After `$value instanceof Box` on `Container<string>&Box`, `$value` is
 * still a `Box` whose `T` is `string`. A tool that keeps `Box` but drops
 * `T` types `$value->value` as `mixed` and stays silent on `takesInt`.
 *
 * Source: carthage-software/mago#2440 (1.55.0).
 *
 * References:
 * - https://github.com/carthage-software/mago/pull/2440
 */

/**
 * @template T
 */
interface Container
{
    /**
     * @return T
     */
    public function get(): mixed;
}

/**
 * @template T
 */
final class Box implements Container
{
    /**
     * @param T $value
     */
    public function __construct(
        public mixed $value,
    ) {
    }

    /**
     * @return T
     */
    public function get(): mixed
    {
        return $this->value;
    }
}

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

/**
 * @param Container<string>&Box $value
 */
function afterInstanceofBox(Container&Box $value): void // T: Container<string>&Box
{
    if ($value instanceof Box) {
        takesString($value->value); // V: T is still string
        takesInt($value->value); // E?: string is not int
    }
}
