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
    if (is_string($b->v)) {
        return $b; // E<phpstan>: does not narrow the object union from is_string($b->v), so keeps StrBox|IntBox and reports return.type // E<phpstan-strict>: same // E<psalm>: InvalidReturnStatement. Mago and Phan narrow to StrBox and stay clean
    }

    throw new \LogicException('not a StrBox');
}
