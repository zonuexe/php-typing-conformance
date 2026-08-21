<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintNullableMismatch;

/**
 * PHPDoc `@param` omitting null when the native type is already nullable.
 *
 * Type checkers do not require PHPDoc to be an exact replica of the native
 * type. `int $arg` versus `@param string $arg` is a contradiction and is
 * rejected. `?int $arg` versus `@param int $arg` is not: the native type
 * already accepts null, so the missing `|null` is documentation completeness.
 * orklah closed the request to warn on that gap as "more suited for a CS
 * tool" (psalm#3785); Ondřej calls the same gap cosmetic (phpstan#6016).
 *
 * This file measures the CS-shaped gap (`?string $name` with `@param string`).
 * Columns that compare the two spellings report it
 * (`PhanTypeMismatchDeclaredParamNullable`, mir `MismatchingDocblockParamType`,
 * NoVerify `funcParamTypeMissMatch`, Qodana `PhpDocSignatureInspection`).
 * Columns that trust the native type stay silent and remain sound. Steins
 * refuses the lint from its own divergence registry; that is not a sign-on
 * to phpstan#7572, which is the property analogue (`@var` vs `public ?string $x`).
 *
 * References:
 * - Psalm   https://github.com/vimeo/psalm/issues/3785
 * - PHPStan https://github.com/phpstan/phpstan/discussions/6016
 * - PHPStan https://github.com/phpstan/phpstan/issues/657
 * - NoVerify funcParamTypeMissMatch patch series (April 2025)
 *
 * @conformance-kind style
 */

/**
 * @param string $name // E[param_mismatch]: nullable typehint is broader than the PHPDoc contract
 */
function greet(
    ?string $name, // E[param_mismatch]: nullable typehint is broader than the PHPDoc contract
): void {
}
