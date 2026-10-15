<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsGeneratorGetReturn;

/**
 * `Generator::getReturn()` and the TReturn parameter.
 *
 * `Generator<TKey, TValue, TSend, TReturn>`: iteration yields TValue and
 * `getReturn()` gives TReturn. The iteration half is the control; the
 * `getReturn()` half asks whether the fourth parameter is bound at all.
 *
 * References:
 * - PHPStan / Psalm Generator stubs
 * - phpdoc_advanced_generator_generic (the yielded-value side)
 */

final class Item
{
    public function label(): string
    {
        return 'item';
    }
}

final class Summary
{
    public function count(): int
    {
        return 1;
    }
}

/** @return \Generator<int, Item, mixed, Summary> */
function produce(): \Generator // T: \Generator<int, Item, mixed, Summary>
{
    yield new Item();

    return new Summary();
}

function consumes(): void
{
    $items = produce();
    foreach ($items as $item) {
        echo $item->label(); // V
    }
    echo $items->getReturn()->count(); // V

    $generator = produce();
    foreach ($generator as $yielded) {
        echo $yielded->count(); // E: TValue is Item, not Summary
    }
    echo $generator->getReturn()->label(); // E: TReturn is Summary, not Item
}
