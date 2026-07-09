<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsOptionalKeyShapeIsList;

/**
 * Optional-key array shapes stay list-compatible because they admit the empty array.
 *
 * A shape whose only keys are optional (for example `array{a?: string}`) includes `[]`
 * as a valid value, and `array_is_list([]) === true`. So `array_is_list($a)` is
 * undecidable (maybe true), not "always false", and narrowing to a list is reachable.
 *
 * Several analyzers historically treated the optional string key as required and
 * collapsed the list check to "always false" / "impossible". Phan alone reports the
 * check as "maybe", which is the runtime-correct verdict.
 *
 * References:
 * - PHPStan  https://github.com/phpstan/phpstan/issues/14938  (fix: phpstan-src#6025)
 * - Psalm    https://github.com/vimeo/psalm/issues/11905      (open)
 * - Mago     https://github.com/carthage-software/mago/issues/2073 (fixed in 1.43)
 * - Cross-tool survey https://github.com/phpstan/phpstan/discussions/14939
 */

/** @param array{a?: string} $a */
function optionalStringKeyIsList(array $a): void // E<noverify>: NoVerify does not parse array shape syntax and flags the annotation itself, not the list narrowing
{
    if (array_is_list($a)) { // E<phpstan>: array{a?: string} admits [], so the check is maybe-true not always-false (#14938) // E<phpstan-strict>: same always-false verdict under strict rules // E<psalm>: TypeDoesNotContainType, reported as never list<mixed> (#11905) // E<mago>: impossible-type-comparison in 1.19, fixed in 1.43 (#2073)
        echo "reached at runtime when \$a === []\n";
    }
}

/** @param array{a?: string} $a */
function optionalStringKeyAssertList(array $a): void // E<noverify>: NoVerify does not parse array shape syntax and flags the annotation itself, not the list narrowing
{
    assert(array_is_list($a)); // E<phpstan>: assert collapses to always-false on the optional-key shape (#14938) // E<phpstan-strict>: same always-false verdict under strict rules // E<psalm>: TypeDoesNotContainType, reported as never list<mixed> (#11905) // E<mago>: impossible-type-comparison in 1.19, fixed in 1.43 (#2073)
}

optionalStringKeyIsList([]);
optionalStringKeyAssertList([]);
