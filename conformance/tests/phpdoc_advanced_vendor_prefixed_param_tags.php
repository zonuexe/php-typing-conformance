<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedParamTags;

/**
 * Vendor-prefixed param tags may or may not be interpreted across analyzers.
 *
 * References:
 * - PHPStan @phpstan-param
 * - Psalm @psalm-param
 * - Phan @phan-param
 */

/**
 * @phpstan-param int $value
 */
function takesPhpStanParam($value): void
{
}

/**
 * @psalm-param int $value
 */
function takesPsalmParam($value): void
{
}

/**
 * @phan-param int $value
 */
function takesPhanParam($value): void // E?: some tools do not treat @phan-param as a full parameter type declaration
{
}

takesPhpStanParam(1);
takesPhpStanParam('x'); // E?: vendor-prefixed phpstan param tag may be honored

takesPsalmParam(1);
takesPsalmParam('x'); // E?: vendor-prefixed psalm param tag may be honored

takesPhanParam(1);
takesPhanParam('x'); // E?: vendor-prefixed phan param tag may be honored
