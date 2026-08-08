<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanParamClosureThis;

/**
 * Cross-tool handling of `@param-closure-this`.
 *
 * Declares what `$this` will be inside a closure argument when the callee
 * rebinds it. Honouring the tag makes `$this->label` resolve on `Host`.
 *
 * Tools that do not model the tag typically reject `$this` in the closure as
 * out of scope (recorded as Fail / false positives on the valid access).
 *
 * References:
 * - PHPStan phpdocs-basics: Callables / `@param-closure-this`
 */

final class Host
{
    public string $label = 'host';
}

/**
 * @param-closure-this Host $callback
 */
function runWithHost(\Closure $callback): void // T: @param-closure-this
{
    $callback->call(new Host());
}

runWithHost(function (): void {
    // $this is Host: label is defined when the tag is honoured.
    echo $this->label;

    // A property that Host does not declare.
    echo $this->missing; // E?: Host has no $missing under @param-closure-this
});
