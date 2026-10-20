<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysIssetNullableFetch;

/**
 * `isset($array[$key])` on a possibly-null element.
 *
 * PHP `isset` is false when the key is missing *or* the value is `null`, so
 * after a successful `isset` the element is the non-null half of `T|null`.
 * Inside `isset`, reading a key that may be absent is the condition, not an
 * undefined-offset error (vimeo/psalm#10756).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/10756
 */

function takesInt(int $value): void
{
}

/**
 * @param array<string, int|null> $array
 */
function afterIsset(array $array): void
{
    if (isset($array['key'])) {
        takesInt($array['key']); // Q: isset excludes null
        echo $array['key'] === null ? 'null' : 'int'; // E?: after isset, === null is always false
    }
}

/**
 * @param array<string, int> $array
 */
function insideIsset(array $array): void
{
    if (isset($array['missing'])) { // Q: offset access inside isset is not an undefined-key error
        takesInt($array['missing']); // V
    }
}
