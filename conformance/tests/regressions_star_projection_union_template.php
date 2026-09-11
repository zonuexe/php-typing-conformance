<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsStarProjectionUnionTemplate;

/**
 * A star projection in one union arm must not unbind T in the others.
 *
 * PHPStan writes `Pair<T, *>` (and Laravel packages write `Relation<T, *, *>`)
 * when only some type arguments matter. The weaker honest reading is to treat
 * `*` as "unspecified" so T still binds from `Box<T>`. Full star-projection
 * semantics are `phpdoc_advanced_phpstan_star_projection`.
 *
 * Psalm 7 rejects `*` as `InvalidDocblock`, discards the whole param tag, and
 * leaves T unbound so a `Box<T>` return degrades to `Box<never>` — callers then
 * see dead-code diagnostics (vimeo/psalm#11931).
 *
 * Source lead: vimeo/psalm#11931.
 */

/**
 * @template TValue
 */
final class Box
{
    /**
     * @param TValue $value
     */
    public function __construct(
        public mixed $value,
    ) {
    }

    /**
     * @return TValue
     */
    public function get(): mixed
    {
        return $this->value;
    }
}

/**
 * @template TFirst
 * @template TSecond
 */
final class Pair
{
    /**
     * @param TFirst $first
     * @param TSecond $second
     */
    public function __construct(
        public mixed $first,
        public mixed $second,
    ) {
    }
}

final class Factory
{
    /**
     * @template T
     *
     * @param Box<T>|Pair<T, *> $subject
     * @return Box<T>
     */
    public static function make(Box|Pair $subject): Box // T: Pair<T, *>
    {
        if ($subject instanceof Box) {
            return $subject; // V
        }

        throw new \InvalidArgumentException('not a Box');
    }

    /**
     * @template T
     *
     * @param Box<T> $subject
     * @return Box<T>
     */
    public static function makeBoxOnly(Box $subject): Box
    {
        return $subject; // V
    }
}

function takesInt(int $value): void
{
}

/**
 * @param Box<int> $box
 */
function consumeStar(Box $box): void
{
    $result = Factory::make($box); // V: T binds from Box<int> regardless of the Pair arm
    takesInt($result->get()); // V
    echo 'reachable after star'; // Q: Box<never> must not mark the rest of the caller dead
}

/**
 * @param Box<int> $box
 */
function consumePlain(Box $box): void
{
    $result = Factory::makeBoxOnly($box); // V: the same call without `*` in the union
    takesInt($result->get()); // V
    echo 'reachable without star'; // V
}

consumeStar(new Box(1)); // V
consumePlain(new Box(1)); // V
