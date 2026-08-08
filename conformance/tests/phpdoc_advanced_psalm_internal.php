<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmInternal\Library;

/**
 * Cross-tool handling of `@psalm-internal`.
 *
 * Unlike bare `@internal`, this tag names the allowed namespace. Callers
 * outside that namespace are reported.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-internal`
 */

/**
 * @psalm-internal Conformance\Tests\PhpdocAdvancedPsalmInternal\Library
 */
final class Secret // T: @psalm-internal
{
    public static function token(): string
    {
        return 'secret';
    }
}

namespace Conformance\Tests\PhpdocAdvancedPsalmInternal\Library\Nested;

use Conformance\Tests\PhpdocAdvancedPsalmInternal\Library\Secret;

// Nested under the allowed namespace: OK.
function inside(): string
{
    return Secret::token();
}

namespace Conformance\Tests\PhpdocAdvancedPsalmInternal\App;

use Conformance\Tests\PhpdocAdvancedPsalmInternal\Library\Secret;

// Outside the allowed namespace: error when the tag is honoured.
function outside(): string
{
    return Secret::token(); // E?: @psalm-internal Library called from App
}
