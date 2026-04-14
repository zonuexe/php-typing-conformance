<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsStaticReturnMismatch;

/**
 * Static return compatibility, analogous to Python class compatibility tests.
 *
 * References:
 * - PHP static return type
 * - python-typing classes / constructors inspiration
 */

final class Builder
{
}

final class BrokenBuilder
{
    public function rebuild(): static // E[static_return+]: static return type requires BrokenBuilder-compatible result
    {
        return new Builder(); // E[static_return+]: static return type requires BrokenBuilder-compatible result
    }
}
