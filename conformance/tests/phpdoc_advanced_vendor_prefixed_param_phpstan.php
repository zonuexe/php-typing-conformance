<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedParamPhpStan;

/**
 * Cross-tool handling of @phpstan-param.
 *
 * References:
 * - PHPStan @phpstan-param
 * - Psalm prefixed param annotations
 */

/**
 * @phpstan-param int $value
 */
function takesPhpStanParam($value): void
{
}

takesPhpStanParam(1);
takesPhpStanParam('x'); // E: vendor-prefixed phpstan param tag should be enforced
