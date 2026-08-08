<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhpstanAssertVariableCertainty;

/**
 * Cross-tool handling of `PHPStan\Testing\assertVariableCertainty()`.
 *
 * Asserts whether a variable is definitely / maybe / never defined at a
 * point. Wrong certainty produces a diagnostic.
 *
 * References:
 * - PHPStan Testing helpers: `assertVariableCertainty` + `TrinaryLogic`
 *
 * @conformance-kind debug
 */

function example(?string $value): void // T: PHPStan\Testing\assertVariableCertainty
{
    if ($value !== null) {
        // Definitely defined here — silence on match (not a probe).
        \PHPStan\Testing\assertVariableCertainty(\PHPStan\TrinaryLogic::createYes(), $value); // E?[noise]
    }

    // Still defined as a parameter (always certain), so Maybe is wrong — probe.
    \PHPStan\Testing\assertVariableCertainty(\PHPStan\TrinaryLogic::createMaybe(), $value); // E?: expected Maybe, actual Yes
}
