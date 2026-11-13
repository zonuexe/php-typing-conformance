<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanThrowsVoid;

/**
 * Cross-tool handling of `@phpstan-throws void`.
 *
 * PHPStan's default is that a missing `@throws` means "may throw something
 * undocumented". `@throws void` is the explicit "this function throws
 * nothing", so a `throw` in the body contradicts the tag.
 *
 * References:
 * - PHPStan "Bring your exceptions under control": `@throws void`
 */

/**
 * @phpstan-throws void
 */
function claimsQuiet(): void // T: @phpstan-throws void
{
    throw new \RuntimeException('nope'); // E?: body throw contradicts @throws void
}

function undocumentedThrow(): void
{
    // Without `@throws void`, PHPStan's default implicitThrows treats an
    // undocumented throw as allowed. A diagnostic here would mean the tool
    // always requires @throws, which is not this tag.
    throw new \RuntimeException('undocumented'); // V
}
