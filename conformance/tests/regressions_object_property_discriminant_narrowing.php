<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsObjectPropertyDiscriminantNarrowing;

/**
 * Narrowing an object union by a discriminating property.
 *
 * `StrBox::$v` is `string` and `IntBox::$v` is `int`, so `is_string($b->v)` holds only for
 * `StrBox`. In that branch `$b` is `StrBox` and `return $b` satisfies the `: StrBox` return
 * type. Analyzers diverge on whether they narrow the *object* union from a check on a
 * discriminating property.
 *
 * PHPStan narrows array-shape discriminated unions (`$a['tag'] === true`) but not the object
 * equivalent (`is_string($b->v)` / `$b->tag === true`), so it keeps `StrBox|IntBox` here and
 * reports a false-positive return.type.
 *
 * Source lead: Mago analyzer test `issue_1093` (`test_property_narrowing`).
 */

final class StrBox
{
    public string $v = '';
}

final class IntBox
{
    public int $v = 0;
}

function pick(StrBox|IntBox $b): StrBox // E<psalm>: declares the return invalid because $b is not narrowed to StrBox (InvalidReturnType)
{
    if (is_string($b->v)) { // E<mir>: over-narrows, so the discriminator is redundant
        return $b; // E<phpstan>: does not narrow the object union from is_string($b->v), so keeps StrBox|IntBox and reports return.type // E<phpstan-strict>: same // E<psalm>: InvalidReturnStatement // E<pzoom>: same return-type mismatch as Psalm // E<intelephense>: same StrBox|IntBox on the return. Mago narrows to StrBox and stays clean. Phan is silent on the unguarded return too, so it never checked
    }

    throw new \LogicException('not a StrBox'); // E?<mir>: unreachable after the over-narrowing
}

function stillUnion(StrBox|IntBox $b): StrBox // E<psalm>: InvalidReturnType on the unguarded return, same finding as pick
{
    return $b; // E: StrBox|IntBox is not StrBox — silence on pick() is narrowing only if this line reports
}
