<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmIgnoreFalsableReturn;

/**
 * Cross-tool handling of `@psalm-ignore-falsable-return`.
 *
 * Same escape hatch as ignore-nullable-return, for `T|false` returns.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-ignore-falsable-return`
 */

/**
 * @psalm-ignore-falsable-return
 * @return string|false
 */
function maybeString() // T: @psalm-ignore-falsable-return
{
    return \rand(0, 1) === 1 ? 'ok' : false;
}

function takesString(string $value): void
{
}

// Honouring the tag means silence here (Psalm baseline). A diagnostic means
// the ignore was not applied — score with // Q?, not // E?.
takesString(maybeString()); // Q?: silence when @psalm-ignore-falsable-return is applied

// Still never a string, whether or not the ignore tag is known.
takesString(false); // E?: false is not a string regardless of ignore-falsable-return
