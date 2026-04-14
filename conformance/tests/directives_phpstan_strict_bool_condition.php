<?php

declare(strict_types=1);

namespace Conformance\Tests\DirectivesPhpstanStrictBoolCondition;

/**
 * PHPStan strict-rules opt-in detection for boolean conditions.
 *
 * References:
 * - PHPStan strict-rules boolean conditions
 */
function isPositive(int $value): bool
{
    if ($value) { // E: strict PHPStan requires an explicit boolean condition
        return true;
    }

    return false;
}
