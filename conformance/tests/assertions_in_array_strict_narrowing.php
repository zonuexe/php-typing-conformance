<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsInArrayStrictNarrowing;

/**
 * Strict `in_array()` narrowing of the needle.
 *
 * A strict `in_array($needle, $haystack, true)` that succeeds proves the
 * needle is one of the haystack's elements, so a `?User` needle searched in a
 * `list<User>` is a User inside the branch. A tool that does not narrow on
 * `in_array` reports a possibly-null method call on a valid line.
 *
 * The loose comparison does not narrow (`null == ''` style coercions), so it
 * stays a plain control of the un-narrowed type.
 *
 * References:
 * - PHPStan InArrayFunctionTypeSpecifyingExtension
 * - Psalm in_array assertion
 */

final class User
{
    public function name(): string
    {
        return 'user';
    }
}

/** @param list<User> $users */
function strictNeedle(?User $candidate, array $users): void
{
    if (in_array($candidate, $users, true)) {
        echo $candidate->name(); // V: the needle is a User here
    }
}

/** @param list<User> $users */
function strictNeedleMissing(?User $candidate, array $users): void
{
    if (in_array($candidate, $users, true)) {
        echo $candidate->missing(); // E: narrowed to User, which has no missing()
    }
}

/** @param 'draft'|'published' $status */
function takesStatus(string $status): void
{
    echo $status;
}

function strictLiteralHaystack(string $status): void
{
    if (in_array($status, ['draft', 'published'], true)) {
        takesStatus($status); // V: narrowed to the haystack's literals
    }
}
