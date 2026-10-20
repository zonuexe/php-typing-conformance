<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsSwitchGetClassGettypeNarrowing;

/**
 * Narrowing the subject of `switch (get_class($x))` and `switch (gettype($x))`.
 *
 * A `case Foo::class:` branch of `switch (get_class($x))` is only entered
 * when `$x` is exactly a Foo, and a `case 'integer':` branch of
 * `switch (gettype($x))` only when `$x` is an int. Inside the branch the
 * variable should carry the matched type rather than its pre-switch union.
 *
 * Each mechanism has three probes, each on its own variable:
 *
 * - a quiet probe: a call that is only valid on the narrowed type. Honour is
 *   silence; a tool that keeps the union reports it.
 * - a reference: the same call with no switch around it, which must fire.
 *   Silence on the quiet probe means something only where this fires.
 * - an impossible check inside the branch (`instanceof Bar` in the Foo case,
 *   `is_string()` in the integer case). It is always false only after
 *   narrowing, so it fires only when the tool narrowed. The same check
 *   without the switch is a `// V` control.
 *
 * Source:
 * - carthage-software/mago#2558 (fixed in Mago 1.58.0)
 */

final class Foo
{
    public function fooOnly(): string
    {
        return 'foo';
    }
}

final class Bar
{
    public function barOnly(): string
    {
        return 'bar';
    }
}

function takesInt(int $value): void
{
    echo $value;
}

function getClassQuiet(Foo|Bar $quiet): void
{
    switch (get_class($quiet)) {
        case Foo::class:
            echo $quiet->fooOnly(); // Q: narrowed to Foo
            break;
        case Bar::class:
            echo $quiet->barOnly(); // Q: narrowed to Bar
            break;
    }
}

function getClassReference(Foo|Bar $unguarded): void
{
    echo $unguarded->fooOnly(); // E: Bar has no fooOnly()
}

function getClassImpossible(Foo|Bar $impossible): void
{
    switch (get_class($impossible)) {
        case Foo::class:
            echo $impossible instanceof Bar ? 'bar' : 'foo'; // E: a Foo is never a Bar
            break;
    }
}

function getClassControl(Foo|Bar $control): void
{
    echo $control instanceof Bar ? 'bar' : 'foo'; // V
}

function gettypeQuiet(int|string $quiet): void
{
    switch (gettype($quiet)) {
        case 'integer':
            takesInt($quiet); // Q: narrowed to int
            break;
    }
}

function gettypeReference(int|string $unguarded): void
{
    takesInt($unguarded); // E: a string is not an int
}

function gettypeImpossible(int|string $impossible): void
{
    switch (gettype($impossible)) {
        case 'integer':
            echo is_string($impossible) ? 'string' : 'int'; // E: an int is never a string
            break;
    }
}

function gettypeControl(int|string $control): void
{
    echo is_string($control) ? 'string' : 'int'; // V
}
