<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsImplicitStringable;

/**
 * An interface that declares `__toString()` satisfies `\Stringable`.
 *
 * `phpdoc_advanced_psalm_stringable_object` measures the PHPDoc spelling
 * `stringable-object`. This file asks the native question: a parameter typed
 * `\Stringable` accepts an interface that declares `__toString()` without
 * extending `\Stringable`.
 *
 * Source: carthage-software/mago#2450 (1.56.0).
 *
 * References:
 * - https://github.com/carthage-software/mago/pull/2450
 */

interface CustomStringable
{
    public function __toString(): string;
}

interface NotStringable
{
    public function name(): string;
}

function takesStringable(\Stringable $value): void
{
}

function fromCustom(CustomStringable $value): void
{
    takesStringable($value); // V: __toString() makes the interface Stringable
}

function fromUnrelated(NotStringable $value): void
{
    takesStringable($value); // E: NotStringable has no __toString()
}
