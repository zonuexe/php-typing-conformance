<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanParamLaterInvokedCallable;

/**
 * Cross-tool handling of `@param-later-invoked-callable`.
 *
 * Free functions default to immediately-invoked. The tag overrides a function
 * parameter so the callable is treated as deferred (not run before the next
 * statement).
 *
 * References:
 * - PHPStan phpdocs-basics: Callables
 * - PHPStan 1.11 release notes
 */

/**
 * @param-later-invoked-callable $callback
 */
function schedule(callable $callback): void // T: @param-later-invoked-callable
{
    // Intentionally not called: models a queue/store API.
}

function takesString(string $value): void
{
}

function example(): void
{
    $name = null;
    schedule(static function () use (&$name): void {
        $name = 'Ada';
    });

    // Under later invocation the assignment is not yet visible.
    takesString($name); // E?: after later-invoked callback, $name may still be null
}
