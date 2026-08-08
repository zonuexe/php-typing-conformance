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

// Supporting tools accept the call; tools that ignore the tag may still warn.
takesString(maybeString()); // E?: tools without ignore-falsable-return still see string|false

takesString(false); // E?: false is not a string regardless of ignore-falsable-return
