<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPsalmCheckType;

/**
 * Cross-tool handling of `@psalm-check-type` / `@psalm-check-type-exact`.
 *
 * Psalm's assert-style inspection tags: the docblock states the type a
 * variable should have at that point, and Psalm reports a `CheckType` issue
 * only where the inferred type does not match. The two spellings differ in
 * how strict the match is — `@psalm-check-type` accepts a subtype (the
 * literal `2` matches `int`), `@psalm-check-type-exact` demands the exact
 * type. A matching assertion is silent, so only the mismatch lines are
 * enforcement probes; the matching lines carry `E?[noise]` to tolerate a
 * tool that parses the tag and stumbles.
 *
 * Attribution: Psalm blames the statement that follows the annotation, not
 * the docblock line, so the probes sit on those statements; the docblock
 * lines are kept free of trailing `//` comments for hygiene.
 *
 * References:
 * - Psalm running_psalm/issues/CheckType.md
 *
 * @conformance-kind debug
 */

function checkTypeExample(): void // T: @psalm-check-type
{
    $x = 2;

    /** @psalm-check-type $x = int */
    echo $x; // E?[noise]: matching assertion — the literal 2 is an int, silence expected

    /** @psalm-check-type $x = 1 */
    echo $x; // E?: 2 is not the literal 1 — the sole enforcement probe
}

function checkTypeExactExample(): void // T: @psalm-check-type-exact
{
    $x = 2;

    /** @psalm-check-type-exact $x = 2 */
    echo $x; // E?[noise]: exact match, silence expected

    /** @psalm-check-type-exact $x = int */
    echo $x; // E?: 2 is not exactly int — the sole enforcement probe
}
