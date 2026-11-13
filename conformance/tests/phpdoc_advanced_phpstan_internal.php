<?php

declare(strict_types=1);

namespace PhpdocAdvancedPhpstanInternalLib;

/**
 * Cross-tool handling of unprefixed `@internal` as PHPStan 2.1.13+ bleeding
 * edge reads it: usages outside the declaration's *top* namespace are
 * reported. Psalm's `@psalm-internal` names the allowed namespace explicitly
 * and is measured separately.
 *
 * The class lives in a dedicated top-level namespace so an in-suite caller
 * can sit outside it. `Conformance\Tests\…` would share the `Conformance`
 * top segment with every other test and could never be "outside".
 *
 * References:
 * - PHPStan phpdocs-basics: Internal symbols (2.1.13 + Bleeding Edge)
 */

/**
 * @internal
 */
final class Secret // T: @internal
{
    public static function token(): string
    {
        return 'secret';
    }
}

// Same namespace as the declaration: OK for both PHPStan's top-namespace
// rule and tools that require an exact namespace match.
function inside(): string
{
    return Secret::token(); // V
}

namespace PhpdocAdvancedPhpstanInternalApp;

// Different top namespace: error when the tag is honoured.
function outside(): string
{
    return \PhpdocAdvancedPhpstanInternalLib\Secret::token(); // E?: @internal Lib called from App
}
