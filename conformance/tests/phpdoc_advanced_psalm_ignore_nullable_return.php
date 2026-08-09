<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmIgnoreNullableReturn;

/**
 * Cross-tool handling of `@psalm-ignore-nullable-return`.
 *
 * Suppresses nullable-return complaints at call sites so a `?T` return can be
 * passed where `T` is required. A deliberate escape hatch, not a type truth.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-ignore-nullable-return`
 */

final class Item
{
}

/**
 * @psalm-ignore-nullable-return
 */
function maybeItem(): ?Item // T: @psalm-ignore-nullable-return
{
    return \rand(0, 1) === 1 ? new Item() : null;
}

function takesItem(Item $item): void
{
}

// Honouring the tag means silence here (Psalm baseline). Quiet is origin-only
// so tools that never warn on ?Item do not earn a free half-point; tools that
// still see the nullable return are noise, not tag enforcement.
takesItem(maybeItem()); // Q?<psalm> // Q?<pzoom> // E?[noise]

// Still never an Item, whether or not the ignore tag is known.
takesItem(null); // E?: null is not an Item regardless of ignore-nullable-return
