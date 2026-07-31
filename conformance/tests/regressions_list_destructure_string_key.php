<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsListDestructureStringKey;

/**
 * Positional list destructuring of a string-keyed array.
 *
 * `[$a, $b] = $stringKeyed` reads the positional integer keys 0 and 1, but a
 * `array<string, int>` has no integer keys — the destructured variables are always
 * undefined at runtime. Analyzers diverge on whether they detect this: Mago reports the
 * string-key / undefined-index mismatch, while others accept the destructuring silently.
 *
 * Source lead: Mago analyzer test `list_destructure_string_key_simple`
 * (crates/analyzer/tests/cases). This is the inverse direction from the narrowing cases —
 * here Mago is the strict tool and the others miss the defect.
 */

/** @return array<string, int> */
function stringKeyed(): array
{
    return ['a' => 1, 'b' => 2];
}

function caller(): void
{
    [$a, $b] = stringKeyed(); // E: offsets 0 and 1 do not exist on array<string, int>, so the destructure cannot succeed

    echo $a; // E<phpstan>: $a is mixed after the failed destructure, not string-convertible // E<phpstan-strict>: same
    echo $b; // E<phpstan>: $b is mixed after the failed destructure, not string-convertible // E<phpstan-strict>: same
}
