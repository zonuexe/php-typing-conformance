<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmInheritors;

/**
 * Cross-tool handling of `@psalm-inheritors`.
 *
 * Restricts which types may extend the annotated class. Psalm's spelling of
 * the same idea as `@phpstan-sealed`.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-inheritors`
 * - Psalm InheritorViolation / InvalidExtendClass
 */

/**
 * @psalm-inheritors AllowedOne|AllowedTwo
 */
abstract class Base // T: @psalm-inheritors
{
}

final class AllowedOne extends Base // V
{
}

final class AllowedTwo extends Base // V
{
}

// Not in the inheritor allow-list.
final class Forbidden extends Base // E?: Forbidden is not allowed as a subtype of Base
{
}
