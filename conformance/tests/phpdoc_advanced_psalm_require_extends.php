<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmRequireExtends;

/**
 * Cross-tool handling of `@psalm-require-extends`.
 *
 * Psalm documents this tag on traits: a using class must extend the named
 * parent. PHPStan's `@phpstan-require-extends` is measured separately
 * (including on interfaces); Psalm's native spelling is trait-only.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-require-extends`
 * - Psalm ExtensionRequirementViolation
 */

class RequiredParent
{
}

/**
 * @psalm-require-extends RequiredParent
 */
trait NeedsParent // T: @psalm-require-extends
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
