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
        echo $this->label; // E?: tools without @phan-closure-scope reject $this here

        echo $this->missing; // E?: Host has no $missing under @phan-closure-scope
    };
}

$printer = makePrinter();
$bound = $printer->bindTo(new Host());
if ($bound !== null) {
    $bound();
}
