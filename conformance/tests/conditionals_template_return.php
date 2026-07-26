<?php

declare(strict_types=1);

namespace Conformance\Tests\ConditionalsTemplateReturn;

/**
 * Conditional return type combined with a template parameter.
 *
 * When `$id` is an int, the return is a single `Item`; when `$id` is a list
 * of ints, the return is a list of `Item`. Analyzers that expand the
 * conditional after template inference reject a list return used as a single
 * Item.
 *
 * References:
 * - PHPStan phpdoc-types: Conditional return types + generics
 * - Psalm conditional_types.md
 */

final class Item
{
    public function __construct(
        public int $id,
    ) {
    }
}

/**
 * @template T of int|list<int>
 * @param T $id
 * @return (T is int ? Item : list<Item>) // E<phan>: Phan cannot extract template-conditional return annotations
 */
function fetch(int|array $id): Item|array // E<phan>: without conditional expansion Phan reports template unused in return
{
    // Body is intentionally trivial so control-flow analysis does not dominate
    // the comparison; the conformance question is the annotated return form.
    if (is_int($id)) {
        return new Item($id);
    }

    return [new Item(0)];
}

function takesItem(Item $item): void
{
}

// Scalar id → single Item.
takesItem(fetch(1));

// List id → list<Item>, not a single Item.
takesItem(fetch([1, 2])); // E?: list branch of conditional return should not satisfy Item
