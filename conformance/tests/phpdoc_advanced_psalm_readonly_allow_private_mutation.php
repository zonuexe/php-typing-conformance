<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmReadonlyAllowPrivateMutation;

/**
 * Cross-tool handling of `@psalm-readonly-allow-private-mutation`.
 *
 * One-line shorthand for `@readonly` + `@psalm-allow-private-mutation`:
 * methods of the declaring class may write the property; outsiders may not.
 * The split two-tag form is measured in `phpdoc_advanced_psalm_allow_private_mutation`.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-readonly-allow-private-mutation`
 */

final class Counter
{
    /** @psalm-readonly-allow-private-mutation */
    public int $value = 0; // T: @psalm-readonly-allow-private-mutation

    public function increment(): void
    {
        // Allowed under the combined tag (Psalm baseline). Quiet is origin-only;
        // foreign tools that treat an unknown tag as frozen @readonly are noise.
        $this->value++; // Q?<psalm> // Q?<pzoom> // E?[noise]
    }
}

function bumpOutside(Counter $counter): void
{
    $counter->value = 99; // E?: readonly-by-phpdoc property assigned outside declaring class
}

$counter = new Counter();
$counter->increment(); // V
bumpOutside($counter);
