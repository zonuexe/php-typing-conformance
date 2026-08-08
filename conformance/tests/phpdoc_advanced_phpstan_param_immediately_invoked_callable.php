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

    // Under immediate invocation the by-ref assignment is visible here.
    takesString($name); // E?: after immediately-invoked callback, $name should be string
}
