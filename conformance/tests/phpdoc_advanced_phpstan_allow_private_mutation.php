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
        // Allowed under the tag (PHPStan baseline). Silence = success.
        $this->value++; // Q?: silence when @phpstan-allow-private-mutation is applied
    }
}

function bumpOutside(Counter $counter): void
{
    $counter->value = 99; // E?: readonly property assigned outside declaring class
}

$counter = new Counter();
$counter->increment();
bumpOutside($counter);
