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
 * Honour is silence on the narrowed `name()` call. A tool that does not
 * narrow reports a possibly-null method call there. The loose comparison
 * (`null == ''` style coercions) is not a control: an unsound nullable
 * reading would fail it for a reason that is not `in_array`.
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
        echo $candidate->name(); // Q: the needle is a User here
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
