<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanRequireImplements;

/**
 * Cross-tool handling of `@phpstan-require-implements`.
 *
 * When a trait carries the tag, using classes must implement the named
 * interface.
 *
 * References:
 * - PHPStan phpdocs-basics: Enforcing implementing an interface for traits
 */

interface RequiredContract
{
}

/**
 * @phpstan-require-implements RequiredContract
 */
trait NeedsContract // T: @phpstan-require-implements
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
