<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedMixin;

/**
 * Cross-tool handling of `@mixin`.
 *
 * The tag tells analyzers that unknown method/property access is delegated to
 * another type. Honouring it means `doA()` on `B` is treated as `A::doA()`.
 *
 * References:
 * - PHPStan phpdocs-basics: Mixins
 * - Psalm supported_annotations.md: @mixins
 */

final class Delegated
{
    public function answer(): int
    {
        return 42;
    }
}

/**
 * @mixin Delegated
 */
final class Facade // T: @mixin
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        if ($name === 'answer') {
            return (new Delegated())->answer();
        }

        throw new \BadMethodCallException($name);
    }
}

function takesInt(int $value): void
{
}

// Mixin makes answer() visible and typed as int. Honouring the tag means this
// is silent; a tool that ignores @mixin routes answer() through __call and sees
// `mixed`, so a diagnostic here means the tag was not applied. Silence is the
// discriminator, so this is a quiet probe — but only for the analyzers that
// both flag `mixed` arguments and would therefore stay silent *because* the
// mixin resolved answer() to int. Tools that never flag `mixed` (phan, phpy,
// …) would be silent regardless, so scoring them here would hand out a free
// pass; they are left as recognition-only instead.
takesInt((new Facade())->answer()); // Q?<phpstan> // Q?<phpstan-strict> // Q?<psalm> // Q?<pzoom> // Q?<mago> // Q?<intelephense> // E?[noise]

// A method neither on Facade nor on the mixin target stays undefined. It is
// caught by __call handling whether or not @mixin is honoured, so it is noise
// for the tag rather than an enforcement probe.
takesInt((new Facade())->missing()); // E?[noise]: missing is not provided by @mixin Delegated
