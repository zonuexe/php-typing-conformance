<?php

declare(strict_types=1);

namespace Conformance\Tests\PropertiesAsymmetricVisibility;

/**
 * PHP 8.4+ asymmetric property visibility.
 *
 * A property that is publicly readable but privately writable must reject
 * external assignment while still allowing external reads.
 *
 * References:
 * - PHP language: asymmetric visibility
 */

final class Counter
{
    public private(set) int $value = 0;

    public function increment(): void
    {
        $this->value++;
    }
}

$counter = new Counter();
echo $counter->value;
$counter->value = 10; // E?: external write to private(set) should be rejected // E<phpstan>: external write to private(set) should be rejected // E<phpstan-strict>: external write to private(set) should be rejected // E<mago>: external write to private(set) should be rejected // E<phan>: external write to private(set) should be rejected // E<intelephense>: external write to private(set) should be rejected
