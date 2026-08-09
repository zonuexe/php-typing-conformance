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
    $callback->call(new Host()); // E?[noise]: some tools reject Closure::call on the parameter type
}

runWithHost(function (): void {
    // $this is Host: label is defined when the tag is honoured. Honouring the
    // tag means silence here; a tool that ignores @param-closure-this rejects
    // `$this` as out of scope, so a diagnostic means the tag was not applied.
    // Scoped to the analyzers that model the tag so silence-by-default tools do
    // not earn a free pass.
    echo $this->label; // Q?<phpstan> // Q?<phpstan-strict> // Q?<psalm> // Q?<pzoom> // Q?<mago> // Q?<phan> // Q?<intelephense> // Q?<phpy> // E?[noise]

    // A property that Host does not declare. Tools that ignore the tag also
    // report this (via out-of-scope `$this`), so it is noise for the tag.
    echo $this->missing; // E?[noise]: Host has no $missing under @param-closure-this
});
