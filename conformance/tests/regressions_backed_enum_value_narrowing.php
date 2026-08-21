<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsBackedEnumValueNarrowing;

/**
 * Narrowing a backed enum by its `->value` comparison.
 *
 * A backed enum's case↔value map is a bijection, so `$s->value === 'H'` uniquely selects
 * `Suit::Hearts`, and the fall-through is `Suit::Spades`. The `match ($s)` that follows is
 * therefore exhaustive with only the `Suit::Spades` arm.
 *
 * Analyzers diverge on whether they propagate a `->value` comparison to the enum case.
 * PHPStan narrows case identity (`$s === Suit::Hearts`) but not the `->value` form, so it
 * keeps the full `Suit` type and reports the `match` as non-exhaustive.
 *
 * Source lead: cross-tool narrowing probe (companion to the class-string case).
 */

enum Suit: string
{
    case Hearts = 'H';
    case Spades = 'S';
}

function describe(Suit $s): string // E<noverify>: NoVerify 0.5.5 does not support enums and treats Suit as an undefined class
{
    if ($s->value === 'H') { // E<noverify>: enum property ->value unresolved because enums are unsupported
        return 'hearts';
    }

    return match ($s) { // E<phpstan>: $s not narrowed by ->value === 'H', so match is seen as non-exhaustive (Suit::Hearts unhandled) // E<phpstan-strict>: same // E<psalm>: UnhandledMatchCondition // E<mago>: match-not-exhaustive // E<pzoom>: same UnhandledMatchCondition as Psalm // E<qodana>: PhpUncoveredEnumCasesInspection. Phan is silent on the unguarded match too, so it never checked exhaustiveness
        Suit::Spades => 'spades', // E<noverify>: enum case constant Suit::Spades unresolved
    };
}

function stillFullSuit(Suit $s): string // E<noverify>: Suit unresolved, same as describe()
{
    return match ($s) { // E: Hearts is unhandled unless ->value already narrowed the subject
        Suit::Spades => 'spades', // E<noverify>: enum case constant unresolved
    };
}
