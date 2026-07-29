<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysShapeDiscriminantMatch;

/**
 * Discriminated union of array shapes, narrowed through `match`
 *
 * A tagged union in PHPDoc: two array shapes sharing a literal-typed `type`
 * key, each carrying a key the other lacks. Matching on the discriminant is
 * supposed to select one member per arm, which decides three things at once —
 * whether the variant-specific read in each arm is a known offset, whether the
 * two literals exhaust the match, and whether reading the *other* variant's
 * key is caught. A tool that keeps the union unnarrowed gets all three wrong:
 * it doubts the valid reads, wants a default arm, and misses the invalid read.
 *
 * References:
 * - PHPStan: match expression narrowing of constant-string unions
 * - Psalm: match on array shape literal keys
 */

/**
 * @param array{type: 'Illust', image_url: non-falsy-string}|array{type: 'Novel', cover_url: non-falsy-string} $work
 */
function coverUrl(array $work): string
{
    // Each arm reads the key only its member has. The two literals cover the
    // whole discriminant type, so no default arm is needed either. A tool that
    // does not narrow reports possibly-undefined offsets or a non-exhaustive
    // match right here, on code the union says is fine.
    return match ($work['type']) {
        'Illust' => $work['image_url'],
        'Novel' => $work['cover_url'],
    };
}

/**
 * @param array{type: 'Illust', image_url: non-falsy-string}|array{type: 'Novel', cover_url: non-falsy-string} $work
 */
function crossedArms(array $work): string
{
    // The same match with the arms swapped: each arm now reads the key its
    // member does not have, which narrowing turns into an undefined offset.
    return match ($work['type']) { // E?: collateral — the failed reads taint the returned type
        'Illust' => $work['cover_url'], // E?: the Illust member has no cover_url key
        'Novel' => $work['image_url'], // E?: the Novel member has no image_url key
    };
}

echo coverUrl(['type' => 'Illust', 'image_url' => 'https://example.com/i.png']);
echo coverUrl(['type' => 'Novel', 'cover_url' => 'https://example.com/c.png']);
