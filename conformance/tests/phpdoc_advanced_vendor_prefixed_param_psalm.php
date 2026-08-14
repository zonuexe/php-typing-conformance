<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedParamPsalm;

/**
 * Cross-tool handling of @psalm-param.
 *
 * References:
 * - Psalm @psalm-param
 * - PHPStan prefixed param annotations
 * - Phan prefixed param aliases
 */

/**
 * @psalm-param int $value
 */
function takesPsalmParam($value): void // T: @psalm-param
{
}

takesPsalmParam(1); // V
takesPsalmParam('x'); // E: vendor-prefixed psalm param tag should be enforced
