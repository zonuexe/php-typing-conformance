<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanParamImmediatelyInvokedCallable;

/**
 * Cross-tool handling of `@param-immediately-invoked-callable`.
 *
 * Free functions default to immediately-invoked; object methods default to
 * later. This tag overrides a method parameter to the immediate schedule.
 *
 * References:
 * - PHPStan phpdocs-basics: Callables
 * - PHPStan 1.11 release notes
 */

final class Runner
{
    /**
     * @param-immediately-invoked-callable $callback
     */
    public function runNow(callable $callback): void // T: @param-immediately-invoked-callable
    {
        $callback();
    }
}

function takesString(string $value): void
{
}

function example(Runner $runner): void
{
    $name = null;
    $runner->runNow(static function () use (&$name): void {
        $name = 'Ada';
    });

    // NOTE: the invocation-timing tags govern checked-exception propagation
    // (@throws / try-catch), not by-ref narrowing. PHPStan infers `'Ada'|null`
    // for $name whether or not the callable is immediately invoked, so this
    // diagnostic fires independently of the tag and must not be scored as tag
    // enforcement. The checked-exceptions rule that the tag actually feeds is
    // not enabled in this harness, so there is no enforcement probe to make.
    takesString($name); // E?[noise]: string|null argument, independent of the tag
}
