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
 * @param non-empty-string $value // E?: some tools do not parse non-empty-string PHPDoc syntax
 */
function takesNonEmptyString(string $value): void // E?: some tools do not accept non-empty-string PHPDoc on string parameters
{
}

/**
 * @param non-falsy-string $value // E?: some tools do not parse non-falsy-string PHPDoc syntax
 */
function takesNonFalsyString(string $value): void // E?: some tools do not accept non-falsy-string PHPDoc on string parameters
{
}

/**
 * @param non-empty-string|false $value
 */
function inspectNonEmptyStringOrFalse($value): void
{
    if ($value !== false) {
        takesNonEmptyString($value);
        takesNonFalsyString($value); // E?: non-empty-string should not automatically narrow to non-falsy-string
        return;
    }

    takesNonEmptyString($value); // E?: false branch should not expose non-empty-string
    takesNonFalsyString($value); // E?: false branch should not expose non-falsy-string
}
