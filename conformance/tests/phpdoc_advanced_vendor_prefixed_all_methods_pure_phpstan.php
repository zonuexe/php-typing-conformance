<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAllMethodsPurePhpStan;

/**
 * Cross-tool handling of `@phpstan-all-methods-pure`
 *
 * Class-level purity: every method is treated as pure unless a method overrides
 * with `@phpstan-impure`. Honouring the tag means reporting side effects in
 * method bodies the same way `@phpstan-pure` does on a single function.
 *
 * Available since PHPStan 2.1.39. The obsolete `@phpstan-all-methods-are-pure`
 * spelling is rejected as an unknown tag and is not tested here.
 *
 * References:
 * - PHPStan phpdocs-basics: Impure functions / all-methods-pure
 * - PHPStan 2.1.39 release notes
 */

/**
 * @phpstan-all-methods-pure
 */
final class PureBox // T: @phpstan-all-methods-pure
{
    public function add(int $left, int $right): int
    {
        // The claim and the body disagree: honouring the class tag means saying so.
        echo 'side effect'; // E?: an echo contradicts @phpstan-all-methods-pure

        return $left + $right;
    }
}

echo (new PureBox())->add(1, 2);
