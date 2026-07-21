<?php

declare(strict_types=1);

namespace Conformance\Tests\DirectivesPhpstanStrictEmpty;

/**
 * PHPStan strict-rules opt-in detection for empty().
 *
 * References:
 * - PHPStan strict-rules disallowed empty
 * - python-typing directives inspiration
 *
 * @conformance-kind style
 */

/**
 * @param array<int> $values
 */
function hasValues(array $values): bool
{
    return empty($values); // E<phpstan-strict>: strict PHPStan disallows empty()
}
