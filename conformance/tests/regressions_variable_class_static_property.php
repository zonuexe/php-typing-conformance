<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsVariableClassStaticProperty;

/**
 * `$class::$prop` when `$class` is a class-string, not a bare class name.
 *
 * `Holder::$count` is a public int. Through `class-string<Holder>` the same
 * read should be int, a string write should be rejected, and the private
 * `$secret` should stay inaccessible. `get_class($holder)` is the same
 * class-string; Psalm 6.x used to analyse that read as a made-up object
 * property (`InvalidPropertyFetch` on `$__fake_var`).
 *
 * Source: vimeo/psalm#12040, from #3609 / #4198.
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/12040
 */

final class Holder
{
    public static int $count = 1;

    private static int $secret = 2;

    public static function secret(): int
    {
        return self::$secret;
    }
}

function takesInt(int $value): void
{
}

/**
 * @param class-string<Holder> $class
 */
function readCount(string $class): void // T: class-string<Holder>
{
    takesInt($class::$count); // Q: class-string<Holder>::$count is int
}

/**
 * @param class-string<Holder> $class
 */
function writeCount(string $class): void // T: class-string<Holder>
{
    $class::$count = 2; // V: int write matches the declared type
    $class::$count = 'no'; // E: string is not int
}

/**
 * @param class-string<Holder> $class
 */
function readSecret(string $class): void // T: class-string<Holder>
{
    takesInt($class::$secret); // E: $secret is private
}

function fromGetClass(Holder $holder): void
{
    $class = get_class($holder);
    takesInt($class::$count); // Q: get_class() is class-string<Holder>
}

takesInt(Holder::$count); // V: the same read with a bare class name
