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

    // NOTE: the invocation-timing tags govern checked-exception propagation
    // (@throws / try-catch), not by-ref narrowing. PHPStan infers `'Ada'|null`
    // for $name whether or not the callable is invoked later, so this diagnostic
    // fires independently of the tag and must not be scored as tag enforcement.
    // The checked-exceptions rule the tag actually feeds is not enabled here, so
    // there is no enforcement probe to make.
    takesString($name); // E?[noise]: string|null argument, independent of the tag
}
