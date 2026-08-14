<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanSealed;

/**
 * Cross-tool handling of `@phpstan-sealed`.
 *
 * Restricts which types may extend/implement the annotated class or interface.
 * Available since PHPStan 2.1.18.
 *
 * References:
 * - PHPStan phpdocs-basics: Sealed classes
 */

/**
 * @phpstan-sealed AllowedOne|AllowedTwo
 */
abstract class Base // T: @phpstan-sealed
{
}

final class AllowedOne extends Base // V
{
}

final class AllowedTwo extends Base // V
{
}

// Not in the sealed allow-list.
final class Forbidden extends Base // E?: Forbidden is not allowed as a subtype of sealed Base
{
}
