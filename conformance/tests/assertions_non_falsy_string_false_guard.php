<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsNonFalsyStringFalseGuard;

/**
 * `!== false` narrowing with non-falsy-string|false.
 *
 * References:
 * - Psalm scalar string refinements
 * - PHPStan string refinement docs // E?: some tools misparse reference text mentioning non-falsy-string
 * - common analyzer narrowing on false checks
 */

/**
 * @param non-falsy-string $value
 */
function takesNonFalsyString(string $value): void // T: non-falsy-string
{
}

/**
 * @param non-empty-string $value
 */
function takesNonEmptyString(string $value): void // T: non-empty-string
{
}

/**
 * @param non-falsy-string|false $value // E?: some tools do not parse non-falsy-string unions in PHPDoc syntax
 */
function inspectNonFalsyStringOrFalse($value): void // E?: some tools do not accept non-falsy-string|false PHPDoc on untyped parameters
{
    if ($value !== false) {
        takesNonFalsyString($value); // E?: some tools fail to preserve non-falsy-string after !== false
        takesNonEmptyString($value); // E?: some tools fail to treat non-falsy-string as non-empty-string
        return;
    }

    takesNonFalsyString($value); // E?: false branch should not expose non-falsy-string
    takesNonEmptyString($value); // E?: false branch should not expose non-empty-string
}
