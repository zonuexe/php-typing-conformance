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
function takesPsalmParam($value): void
{
}

takesPsalmParam(1);
takesPsalmParam('x'); // E?: vendor-prefixed psalm param tag may be honored
