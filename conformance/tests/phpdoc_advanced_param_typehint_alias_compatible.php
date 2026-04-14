<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedParamTypehintAliasCompatible;

use Conformance\Tests\PhpdocAdvancedParamTypehintAliasCompatible\Domain\Item as AliasItem;

/**
 * Imported aliases should remain compatible with canonical PHPDoc names.
 *
 * References:
 * - NoVerify funcParamTypeMissMatch patch series (April 2025)
 */

/**
 * @param \Conformance\Tests\PhpdocAdvancedParamTypehintAliasCompatible\Domain\Item $item
 */
function consumeItem(AliasItem $item): void
{
}

namespace Conformance\Tests\PhpdocAdvancedParamTypehintAliasCompatible\Domain;

final class Item
{
}
