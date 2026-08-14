<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedParamPhan;

/**
 * Cross-tool handling of @phan-param.
 *
 * References:
 * - Phan @phan-param
 * - PHPStan prefixed param annotations
 * - Psalm prefixed param annotations
 */

/**
 * @phan-param int $value
 */
function takesPhanParam($value): void // T: @phan-param
{
}

takesPhanParam(1); // V
takesPhanParam('x'); // E: vendor-prefixed phan param tag should be enforced
