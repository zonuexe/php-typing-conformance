<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintArrayNullableMismatch;

/**
 * PHPDoc `@param` omitting null when the native array type is already nullable.
 *
 * Array form of `phpdoc_advanced_param_typehint_nullable_mismatch.php`:
 * `@param list<int> $items` with `?array $items` is a documentation subset,
 * not a contradiction (`int $arg` vs `@param string $arg`). Type checkers
 * need not replicate native nullability in PHPDoc; that gap is CS
 * (psalm#3785, phpstan#6016). Not phpstan#7572 (properties).
 *
 * References:
 * - Psalm   https://github.com/vimeo/psalm/issues/3785
 * - PHPStan https://github.com/phpstan/phpstan/discussions/6016
 * - NoVerify funcParamTypeMissMatch patch series (April 2025)
 *
 * @conformance-kind style
 */

/**
 * @param list<int> $items // E[array_param_mismatch]: nullable array typehint is broader than the PHPDoc contract
 */
function takesItems(
    ?array $items, // E[array_param_mismatch]: nullable array typehint is broader than the PHPDoc contract
): void {
}
