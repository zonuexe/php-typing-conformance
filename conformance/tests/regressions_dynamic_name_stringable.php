<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsDynamicNameStringable;

/**
 * Dynamic names are not all string-cast the same way.
 *
 * Property names (and variable-variables) go through string casting: a
 * `Stringable` object is a valid name, a non-stringable object is a TypeError.
 * Method names must be actual strings — even `__toString()` is not enough
 * ("Method name must be a string").
 *
 * PHPStan 2.2.15 reports the non-stringable cases on bleeding edge
 * (`checkNonStringableDynamicAccess`, phpstan-src#5871 / phpstan#4710). Mago
 * 1.50.0 started accepting `Stringable` property names (and variable-variable
 * reads). Variable-variables themselves are not probed here: phpstan-strict
 * bans `$$` outright, which would fire on the Stringable control for a reason
 * that is not the name's type.
 *
 * References:
 * - https://github.com/phpstan/phpstan-src/pull/5871
 * - https://github.com/phpstan/phpstan/issues/4710
 * - https://github.com/carthage-software/mago/releases/tag/1.50.0
 */

final class Name implements \Stringable
{
    #[\Override]
    public function __toString(): string
    {
        return 'id';
    }
}

final class NotStringable
{
}

final class Target
{
    public int $id = 1;

    public function id(): int
    {
        return 1;
    }
}

function takesMixed(mixed $value): void
{
}

function propertyStringable(Name $name, Target $target): void
{
    takesMixed($target->{$name}); // Q?: PHP string-casts Stringable property names. mago then warns that the resulting string is not a literal; that warning is not a rejection of Stringable
}

function propertyNonStringable(NotStringable $name, Target $target): void
{
    takesMixed($target->{$name}); // E: non-stringable object is not a property name
}

function methodString(Target $target): void
{
    takesMixed($target->id()); // V: the method exists when named statically. Dynamic string names are the E lines; phpstan-strict bans `$obj->{$name}()` itself
}

function methodStringable(Name $name, Target $target): void
{
    takesMixed($target->{$name}()); // E: method names must be strings, not Stringable
}

function methodNonStringable(NotStringable $name, Target $target): void
{
    takesMixed($target->{$name}()); // E: non-stringable object is not a method name
}
