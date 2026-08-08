<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanAllowPrivateMutation;

/**
 * Cross-tool handling of separate `@readonly` + `@phpstan-allow-private-mutation`.
 *
 * Sibling of the combined `@phpstan-readonly-allow-private-mutation` spelling.
 *
 * References:
 * - PHPStan phpdocs-basics: Readonly properties / allow private mutation
 */

final class Counter
{
    /**
     * @readonly
     * @phpstan-allow-private-mutation
     */
    public int $value = 0; // T: @phpstan-allow-private-mutation

    public function increment(): void
    {
        // Allowed under @phpstan-allow-private-mutation; tools that only see
        // @readonly may still flag this write.
        $this->value++; // E?: tools that ignore allow-private-mutation treat @readonly strictly
    }
}

function bumpOutside(Counter $counter): void
{
    $counter->value = 99; // E?: readonly property assigned outside declaring class
}

$counter = new Counter();
$counter->increment();
bumpOutside($counter);
