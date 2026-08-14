<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanReadonlyAllowPrivateMutation;

/**
 * Cross-tool handling of `@phpstan-readonly-allow-private-mutation`.
 *
 * Combined form of `@readonly` + private mutation allowance: methods of the
 * declaring class may write the property; outsiders may not.
 *
 * References:
 * - PHPStan phpdocs-basics: Readonly properties / allow private mutation
 */

final class Counter
{
    /** @phpstan-readonly-allow-private-mutation */
    public int $value = 0; // T: @phpstan-readonly-allow-private-mutation

    public function increment(): void
    {
        // Allowed under the combined tag (PHPStan baseline). Quiet probes are
        // origin-only so tools that never model the tag do not earn a free
        // half-point from silence; foreign @readonly rejections are noise.
        $this->value++; // Q?<phpstan> // Q?<phpstan-strict> // E?[noise]
    }
}

function bumpOutside(Counter $counter): void
{
    $counter->value = 99; // E?: readonly-by-phpdoc property assigned outside declaring class
}

$counter = new Counter();
$counter->increment(); // V
bumpOutside($counter);
