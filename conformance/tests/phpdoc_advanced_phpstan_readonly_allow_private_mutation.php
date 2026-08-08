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
        $this->value++;
    }
}

function bumpOutside(Counter $counter): void
{
    $counter->value = 99; // E?: readonly-by-phpdoc property assigned outside declaring class
}

$counter = new Counter();
$counter->increment();
bumpOutside($counter);
