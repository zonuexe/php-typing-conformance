<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhanNonNullMixed;

/**
 * `non-null-mixed` is a Phan-only refinement of `mixed`.
 *
 * `non-null-mixed` is `mixed` with `null` excluded. Phan models it; other
 * analyzers do not recognize the keyword and fall back to plain `mixed`, so
 * they accept a null argument that Phan rejects.
 *
 * References:
 * - Phan Type/NonNullMixedType.php (`non-null-mixed`)
 */

/**
 * @param non-null-mixed $value
 */
function acceptsNonNull($value): void
{
}

// Any non-null value satisfies the parameter.
acceptsNonNull(5);

// Null is excluded for analyzers that model it; others fall back to `mixed`.
acceptsNonNull(null); // E?: null is excluded from non-null-mixed
