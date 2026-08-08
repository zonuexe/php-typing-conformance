<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedVendorPrefixedAllMethodsImpurePhpStan;

/**
 * Cross-tool handling of `@phpstan-all-methods-impure`
 *
 * Class-level impurity: method return values are not remembered across calls.
 * After `if ($coin->flip() !== null)`, a second `$coin->flip()` is still
 * `?string` rather than narrowed to `string`. Tools that ignore the tag keep
 * treating non-void methods as pure by default and accept the second call as
 * `string`.
 *
 * Available since PHPStan 2.1.39. The obsolete `@phpstan-all-methods-are-impure`
 * spelling is rejected as an unknown tag and is not tested here.
 *
 * References:
 * - PHPStan phpdocs-basics: Impure functions / all-methods-impure
 * - PHPStan blog: Remembering and forgetting returned values
 */

/**
 * @phpstan-all-methods-impure
 */
final class Coin // T: @phpstan-all-methods-impure
{
    public function flip(): ?string
    {
        return \rand(0, 1) === 1 ? 'heads' : null;
    }
}

function demo(Coin $coin): void
{
    if ($coin->flip() !== null) {
        // Under the class-level impure claim the second call is still ?string.
        // Ignoring the tag remembers the first result as string.
        echo \strlen($coin->flip()); // E?: second flip() is still string|null under @phpstan-all-methods-impure
    }
}
