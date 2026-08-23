<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsOptionalExtraKeyIsList;

/**
 * Optional extra non-list key stays list-compatible.
 *
 * `array{0: int, a?: string}` admits the value `[0 => 1]` (the optional string key `a` is
 * absent), and `array_is_list([0 => 1]) === true`. So `array_is_list($b)` is maybe-true,
 * not "always false", and the list branch is reachable. This is the second reproduction
 * from phpstan#14938 — the optional-extra-key variant of the optional-key family (see
 * regressions_optional_key_shape_is_list.php for the base case).
 *
 * References:
 * - PHPStan  https://github.com/phpstan/phpstan/issues/14938  (fix: phpstan-src#6025)
 * - Cross-tool survey https://github.com/phpstan/phpstan/discussions/14939
 */

/** @param array{0: int, a?: string} $b */
function optionalExtraKeyIsList(array $b): void // E<noverify>: NoVerify does not parse array shape syntax and flags the annotation itself
{
    if (array_is_list($b)) { // E<psalm>: TypeDoesNotContainType, reported as never list // E<pzoom>: same TypeDoesNotContainType as Psalm // E<mir>: RedundantCondition — collapses the check. PHPStan reported always-false through 2.2.8 (#14938) and is silent since 2.2.9. Mago reported impossible-type-comparison in 1.19 and is silent since 1.43
        echo "reached at runtime when \$b === [0 => 1]\n";
    }
}

optionalExtraKeyIsList([0 => 1]);
