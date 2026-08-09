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

// Honouring the tag means silence here (Psalm baseline). A diagnostic means
// the ignore was not applied — score with // Q?, not // E?.
takesItem(maybeItem()); // Q?: silence when @psalm-ignore-nullable-return is applied

// Still never an Item, whether or not the ignore tag is known.
takesItem(null); // E?: null is not an Item regardless of ignore-nullable-return
