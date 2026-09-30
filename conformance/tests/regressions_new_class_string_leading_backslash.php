<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsNewClassStringLeadingBackslash;

/**
 * `new $c` when `$c` is a class string with a leading backslash.
 *
 * `"\\DateTimeImmutable"` and `"DateTimeImmutable"` name the same class. A
 * lookup that keeps the slash misses the constructor and skips
 * `InterfaceInstantiation` for `"\\Countable"`. The slash-free string is the
 * control: a diagnostic there means the tool does not instantiate from a
 * string at all, not that it mishandles the slash.
 *
 * Source: vimeo/psalm#12039, from #5679.
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/12039
 */

function takesImmutable(\DateTimeImmutable $value): void
{
}

function fromPlainString(): void
{
    $class = 'DateTimeImmutable';
    takesImmutable(new $class()); // Q?: some tools ban `new $string` entirely (`InvalidStringClass`); silence here is the slash-free control
}

function fromLeadingSlash(): void
{
    $class = '\\DateTimeImmutable';
    takesImmutable(new $class()); // Q?: a leading backslash still names DateTimeImmutable when `new $string` is allowed
}

function interfaceWithSlash(): void
{
    $class = '\\Countable';
    new $class(); // E: Countable is an interface
}

function interfaceWithoutSlash(): void
{
    $class = 'Countable';
    new $class(); // E: the same without a slash, so a hit here is not the slash bug
}
