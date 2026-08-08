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

// Supporting tools accept the call; tools that ignore the tag may still warn.
takesItem(maybeItem()); // E?: tools without ignore-nullable-return still see ?Item

// A call site that should still be rejected if the ignore is not a blanket.
takesItem(null); // E?: null is not an Item regardless of ignore-nullable-return
