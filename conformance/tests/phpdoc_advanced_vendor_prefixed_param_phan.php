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
function takesPhanParam($value): void // E?: some tools do not treat @phan-param as a full parameter type declaration
{
}

takesPhanParam(1);
takesPhanParam('x'); // E: vendor-prefixed phan param tag should be enforced
