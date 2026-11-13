<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmRequireImplements;

/**
 * Cross-tool handling of `@psalm-require-implements`.
 *
 * When a trait carries the tag, using classes must implement the named
 * interface. Paired with the PHPStan spelling under the other prefix.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-require-implements`
 * - Psalm ImplementationRequirementViolation
 */

interface RequiredContract
{
}

/**
 * @psalm-require-implements RequiredContract
 */
trait NeedsContract // T: @psalm-require-implements
{
}

// Missing the required interface (diagnostic may land on the class or the use).
final class BadUser // E?[req]: using class must implement RequiredContract
{
    use NeedsContract; // E?[req]: using class must implement RequiredContract
}

// Satisfies the requirement.
final class GoodUser implements RequiredContract // V
{
    use NeedsContract;
}
