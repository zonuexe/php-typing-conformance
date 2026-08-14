<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsNonEmptyStringFalseGuard;

/**
 * `!== false` narrowing with non-empty-string|false.
 *
 * References:
 * - Psalm scalar string refinements
 * - PHPStan scalar string refinements
 * - common analyzer narrowing on false checks
 */

/**
 * @param non-empty-string $value
 */
function takesNonEmptyString(string $value): void // T: non-empty-string
{
}

/**
 * @param non-falsy-string $value
 */
function takesNonFalsyString(string $value): void // T: non-falsy-string
{
}

/**
 * @param non-empty-string|false $value
 */
function inspectNonEmptyStringOrFalse($value): void
{
    if ($value !== false) {
        takesNonEmptyString($value); // V
        takesNonFalsyString($value); // E?: non-empty-string should not automatically narrow to non-falsy-string
        return;
    }

    takesNonEmptyString($value); // E?: false branch should not expose non-empty-string
    takesNonFalsyString($value); // E?: false branch should not expose non-falsy-string
}
