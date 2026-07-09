<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsStringNarrowingAssertIfTrue;

/**
 * Custom @assert-if-true predicate narrowing a string to a string subtype.
 *
 * A user-defined boolean predicate annotated to narrow its argument to `non-empty-string`
 * should let a following call that requires `non-empty-string` type-check. Analyzers
 * diverge on whether they honor the (non-vendor-prefixed) `@assert-if-true` form and
 * propagate the narrowed string subtype through the guarded branch.
 *
 * Source lead: Mago analyzer test `reconcile_non_empty_string`
 * (crates/analyzer/tests/cases). Mago narrows and accepts the guarded call; this file
 * records every tool's verdict on the same construct.
 */

/**
 * @assert-if-true non-empty-string $s
 */
function isNonEmpty(string $s): bool
{
    return $s !== '';
}

/** @param non-empty-string $s */
function takesNonEmpty(string $s): void // E<noverify>: NoVerify cannot evaluate the non-empty-string param type and flags the signature itself
{
    echo $s;
}

function caller(string $s): void
{
    if (isNonEmpty($s)) {
        takesNonEmpty($s); // E<phpstan>: does not narrow $s to non-empty-string through the bare @assert-if-true predicate, so reports argument.type // E<phpstan-strict>: same coercion under strict rules // E<psalm>: reports ArgumentTypeCoercion; parent type stays string. Mago and Phan honor the assertion and stay clean
    }
}
