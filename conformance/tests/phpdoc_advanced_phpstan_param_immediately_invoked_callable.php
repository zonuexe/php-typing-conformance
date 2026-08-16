<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanParamImmediatelyInvokedCallable;

/**
 * Cross-tool handling of `@param-immediately-invoked-callable`.
 *
 * Object methods default to later-invoked. The tag overrides a method
 * parameter so a callable argument is treated as run before the next
 * statement. That makes exceptions thrown inside the callback part of
 * the caller's `@throws` (PHPStan `throws.void` when the caller claims
 * to throw nothing).
 *
 * By-ref narrowing is not this tag's job: PHPStan still infers
 * `'Ada'|null` after a callback that assigns through a reference,
 * whether or not the callable is immediately invoked.
 *
 * References:
 * - PHPStan phpdocs-basics: Callables
 * - PHPStan 1.11 release notes
 */

final class Boom extends \RuntimeException
{
}

final class Runner
{
    /**
     * @param callable $callback
     * @param-immediately-invoked-callable $callback
     */
    public function runNow(callable $callback): void // T: @param-immediately-invoked-callable
    {
        $callback();
    }

    /** @param callable $callback */
    public function runLater(callable $callback): void
    {
    }
}

/**
 * @phpstan-throws void
 */
function callsImmediate(Runner $runner): void
{
    $runner->runNow(static function (): void { throw new Boom('now'); }); // E?: immediately-invoked callback throw contradicts @throws void
}

/**
 * @phpstan-throws void
 */
function callsLater(Runner $runner): void
{
    $runner->runLater(static function (): void { throw new Boom('later'); }); // V: default later-invoked, so @throws void still holds
}
