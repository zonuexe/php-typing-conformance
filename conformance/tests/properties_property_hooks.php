<?php

declare(strict_types=1);

namespace Conformance\Tests\PropertiesPropertyHooks;

/**
 * PHP 8.4+ property hooks: typed set side.
 *
 * A hooked property declared as `string` should reject assignment of an int
 * at the write site, the same way a plain typed property would.
 *
 * References:
 * - PHP language: property hooks
 * - PHPStan PHP 8.4 property hooks support
 */

final class HookedName
{
    public string $name = '' {
        set(string $value) {
            $this->name = $value;
        }
    }
}

$object = new HookedName();
$object->name = 'ok';
$object->name = 1; // E?: int is not accepted by hooked string property // E<phpstan>: int is not accepted by hooked string property // E<phpstan-strict>: int is not accepted by hooked string property // E<psalm>: int is not accepted by hooked string property // E<mago>: int is not accepted by hooked string property // E<mir>: int is not accepted by hooked string property // E<phan>: int is not accepted by hooked string property // E<intelephense>: int is not accepted by hooked string property
