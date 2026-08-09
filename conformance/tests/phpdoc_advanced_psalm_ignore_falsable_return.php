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

// Honouring the tag means silence here (Psalm baseline). Quiet is origin-only
// so tools that never warn on string|false do not earn a free half-point;
// tools that still see string|false are noise, not tag enforcement.
takesString(maybeString()); // Q?<psalm> // Q?<pzoom> // E?[noise]

// Still never a string, whether or not the ignore tag is known.
takesString(false); // E?: false is not a string regardless of ignore-falsable-return
