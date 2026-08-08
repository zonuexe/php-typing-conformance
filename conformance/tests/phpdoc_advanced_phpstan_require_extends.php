<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanRequireExtends;

/**
 * Cross-tool handling of `@phpstan-require-extends`.
 *
 * When an interface carries the tag, implementing classes must extend the
 * named parent. Useful for `@property` on interfaces under PHP 8.2+.
 *
 * References:
 * - PHPStan phpdocs-basics: Enforcing class inheritance for interfaces and traits
 */

class RequiredParent
{
}

/**
 * @phpstan-require-extends RequiredParent
 */
interface NeedsParent // T: @phpstan-require-extends
{
}

// Missing the required extends.
final class BadChild implements NeedsParent // E?: implementing class must extend RequiredParent
{
}

// Satisfies the requirement.
final class GoodChild extends RequiredParent implements NeedsParent
{
}
