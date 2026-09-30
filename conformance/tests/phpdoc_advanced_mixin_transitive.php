<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedMixinTransitive;

/**
 * Transitive `@mixin` (depth ≥ 2).
 *
 * `Outer` mixes in `Middle`, which mixes in `Inner`. Honouring the chain makes
 * `Outer::answer()` the `int` from `Inner`. A tool that only walks one hop
 * sees `Middle` with no `answer()` and routes the call through `__call` as
 * `mixed`. Depth one is already measured in `phpdoc_advanced_mixin`.
 *
 * Source: vimeo/psalm#11868 (6.19.0), from Laravel empty query-builder contracts.
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/11868
 */

final class Inner
{
    public function answer(): int
    {
        return 42;
    }
}

/**
 * @mixin Inner
 */
final class Middle // T: @mixin
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        if ($name === 'answer') {
            return (new Inner())->answer();
        }

        throw new \BadMethodCallException($name);
    }
}

/**
 * @mixin Middle
 */
final class Outer // T: @mixin
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        if ($name === 'answer') {
            return (new Middle())->answer();
        }

        throw new \BadMethodCallException($name);
    }
}

function takesInt(int $value): void
{
}

// Depth one, for contrast: Middle declares @mixin Inner.
takesInt((new Middle())->answer()); // Q?<phpstan> // Q?<phpstan-strict> // Q?<psalm> // Q?<pzoom> // Q?<mago> // Q?<intelephense> // E?[noise]

// Depth two: Outer only declares @mixin Middle.
takesInt((new Outer())->answer()); // Q?<phpstan> // Q?<phpstan-strict> // Q?<psalm> // Q?<pzoom> // Q?<mago> // Q?<intelephense> // E?[noise]
