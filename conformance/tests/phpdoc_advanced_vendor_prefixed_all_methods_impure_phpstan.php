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
        // Under the class-level impure claim the second call is still ?string,
        // so strlen() rejects it. Only PHPStan memoizes return values by
        // default, so this is the one tool the tag can be measured on: every
        // other analyzer already treats a second call as ?string regardless of
        // the tag, so its diagnostic here is baseline behaviour, not tag
        // enforcement.
        echo \strlen($coin->flip()); // E?<phpstan> // E?<phpstan-strict> // E?[noise]
    }
}
