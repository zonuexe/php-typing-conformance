<?php

declare(strict_types=1);

namespace Conformance\Tests\PropertiesUninitializedRead;

/**
 * Typed property reads before initialization.
 *
 * A typed property with no constructor assignment is uninitialized until
 * written. Reading it is a runtime TypeError. Tools that only flag a
 * missing constructor still count: they saw the unguarded property.
 *
 * References:
 * - PHP typed properties
 * - python-typing attribute initialization inspiration
 */

final class User // E[uninit]
{
    public string $name; // E[uninit]: typed property without guaranteed initialization // E<psalm>: MissingConstructor on the declaration, not the read // E<pzoom>: same MissingConstructor as Psalm
}

$user = new User();
echo $user->name; // E[uninit]: read of a possibly uninitialized typed property
