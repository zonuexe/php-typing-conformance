<?php

declare(strict_types=1);

namespace Conformance\Tests\PropertiesUninitializedRead;

/**
 * Typed property reads before initialization.
 *
 * References:
 * - PHP typed properties
 * - python-typing attribute initialization inspiration
 */

final class User
{
    public string $name; // E<psalm>: typed property is declared without guaranteed initialization
}

$user = new User();
echo $user->name;
