<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysWrittenByReference;

/**
 * Array elements written through a foreach reference update the array type.
 *
 * After `foreach ($ints as &$item) { $item = 'x'; }`, `$ints` is no longer
 * a `list<int>`. A tool that types the reference as `int` but never writes
 * that back into the array still accepts `takesIntList($ints)`.
 *
 * Source: vimeo/psalm#12154 (7.0.0-rc2).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/12154
 */

/**
 * @param list<int> $ints
 */
function takesIntList(array $ints): void
{
}

/**
 * @param list<int> $ints
 */
function writesStringThroughReference(array $ints): void
{
    $before = $ints;
    takesIntList($before); // V

    foreach ($ints as &$item) {
        $item = 'x';
    }
    unset($item);

    takesIntList($ints); // E: the array now holds strings
}

