<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsKeyLooseNarrowing;

/**
 * Loose comparison against a non-numeric string literal narrows a key union.
 *
 * After `$key == 'foo'` on `'foo'|'bar'|int`, `$key` is `'foo'`. The E probe
 * is a value the original union admits (`'bar'`) that the narrowed type
 * excludes — a hit on `takesInt` would fire even without narrowing, because
 * the union still contains `'foo'|'bar'`.
 *
 * Source: vimeo/psalm#12153 (7.0.0-rc2).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/12153
 */

/**
 * @param 'foo' $value
 */
function takesFoo($value): void
{
}

/**
 * @param int $value
 */
function takesInt($value): void
{
}

/**
 * @param 'foo'|'bar'|int $key
 */
function afterLooseFoo(string|int $key): void
{
    if ($key == 'foo') {
        takesFoo($key); // Q: == 'foo' narrows the union to 'foo'
        echo $key === 'bar' ? 'bar' : 'foo'; // E?: after == 'foo', === 'bar' is always false
    }
}

/**
 * @param 'foo'|'bar'|int $key
 */
function remainingIsInt(string|int $key): void
{
    if ($key == 'foo' || $key == 'bar') {
        return;
    }

    takesInt($key); // Q: the remaining case is int
    takesFoo($key); // E?: int is not 'foo'
}
