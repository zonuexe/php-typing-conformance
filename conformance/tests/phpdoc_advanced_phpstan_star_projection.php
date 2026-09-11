<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanStarProjection;

/**
 * `Collection<*>` (star projection)
 *
 * A PHPStan call-site variance spelling. `*` as a type argument means the
 * function accepts a collection of *any* item type, and combines the write
 * restriction of covariance (`add()` expects `never`) with the read
 * restriction of contravariance (`get()` is `mixed`). It is not
 * `Collection<mixed>`: an invariant `Collection<mixed>` still rejects
 * `Collection<int>`.
 *
 * Mago implements it too. Psalm rejects `*` as `InvalidDocblock` and
 * discards the whole annotation (vimeo/psalm#11931).
 *
 * References:
 * - PHPStan phpdoc-types: Generics (`Collection<*>`)
 * - PHPStan "A guide to call-site generic variance" (star projections)
 * - https://github.com/vimeo/psalm/issues/11931
 */

/**
 * @template T
 */
final class Collection
{
    /**
     * @param T $item
     */
    public function __construct(
        public mixed $item,
    ) {
    }

    /**
     * @param T $item
     */
    public function add($item): void
    {
        $this->item = $item;
    }

    /**
     * @return T
     */
    public function get(): mixed
    {
        return $this->item;
    }

    public function count(): int
    {
        return 1;
    }
}

/**
 * @return Collection<int>
 */
function ints(): Collection
{
    return new Collection(0);
}

/**
 * @return Collection<string>
 */
function strings(): Collection
{
    return new Collection('x');
}

/**
 * @param Collection<*> $collection
 */
function printSize(Collection $collection): void // T: Collection<*>
{
    echo $collection->count(); // V: count() does not mention T
}

/**
 * @param Collection<*> $collection
 */
function writeStar(Collection $collection): void // T: Collection<*>
{
    $collection->add(1); // E?: add() on a star projection expects never
}

/**
 * @param Collection<int> $collection
 */
function writeInt(Collection $collection): void
{
    $collection->add(1); // V: the same write is valid on Collection<int>
}

/**
 * @param Collection<mixed> $collection
 */
function takesMixed(Collection $collection): void
{
}

printSize(ints()); // V
printSize(strings()); // V
writeStar(ints()); // V
writeInt(ints()); // V

// Contrast: invariant Collection<mixed> does not accept Collection<int>. A
// tool that read `*` as `mixed` rejects the V lines above the same way.
takesMixed(ints()); // E?[noise]: Collection<int> is not Collection<mixed>
