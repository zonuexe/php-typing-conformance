<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhanClosureScope;

/**
 * Cross-tool handling of `@phan-closure-scope`.
 *
 * Declares the `$this` type inside a returned/created closure (Phan-specific).
 * Parallel idea to PHPStan `@param-closure-this`, but for the closure's own
 * declared scope rather than a parameter rebinding.
 *
 * References:
 * - Phan Annotating-Your-Source-Code: `@phan-closure-scope`
 */

final class Host
{
    public string $label = 'host';
}

/**
 * @return \Closure
 * @phan-closure-scope Host
 */
function makePrinter(): \Closure // T: @phan-closure-scope
{
    return function (): void {
        // Tools that honour the tag resolve $this as Host; others reject $this.
        // Silence is the discriminator, so this is scoped to Phan rather than
        // awarding a free pass to tools that do not model the tag.
        echo $this->label; // Q?<phan> // E?[noise]

        // A tool that ignores @phan-closure-scope also reports this line because
        // `$this` is unavailable, so the diagnostic is noise for tag enforcement.
        echo $this->missing; // E?[noise]: Host has no $missing under @phan-closure-scope
    };
}

$printer = makePrinter();
$bound = $printer->bindTo(new Host()); // E?[noise]: tooling noise on rebinding, not the tag under test
if ($bound !== null) {
    $bound(); // E?[noise]: call-site tooling noise after bindTo
}
