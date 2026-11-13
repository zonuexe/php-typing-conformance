<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanRequireExtendsTrait;

/**
 * Cross-tool handling of `@phpstan-require-extends` on a trait.
 *
 * PHPStan documents the tag on interfaces *and* traits. The interface form
 * is `phpdoc_advanced_phpstan_require_extends`; this file measures the trait
 * form, which is also Psalm's native `@psalm-require-extends` shape.
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
trait NeedsParent // T: @phpstan-require-extends
{
}

// Missing the required extends (diagnostic may land on the class or the use).
final class BadUser // E?[req]: using class must extend RequiredParent
{
    use NeedsParent; // E?[req]: using class must extend RequiredParent
}

// Satisfies the requirement.
final class GoodUser extends RequiredParent // V
{
    use NeedsParent;
}
