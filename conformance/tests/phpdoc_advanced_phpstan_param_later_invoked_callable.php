<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanParamLaterInvokedCallable;

/**
 * Cross-tool handling of `@param-later-invoked-callable`.
 *
 * Free functions default to immediately-invoked. The tag overrides a
 * function parameter so the callable is treated as deferred. A `try`
 * around the call then cannot see exceptions thrown inside the
 * callback, which is a dead catch once the callee is known not to
 * throw (`@phpstan-throws void` turns off PHPStan's implicit-throws
 * default without using the `@throws void` spelling other tools
 * reject as a reserved word).
 *
 * References:
 * - PHPStan phpdocs-basics: Callables
 * - PHPStan 1.11 release notes
 */

final class Boom extends \RuntimeException
{
}

/**
 * @param-later-invoked-callable $callback
 * @phpstan-throws void
 */
function schedule(callable $callback): void // T: @param-later-invoked-callable
{
}

/**
 * @phpstan-throws void
 */
function runNow(callable $callback): void
{
}

function example(): void
{
    try {
        schedule(static function (): void {
            throw new Boom('later');
        });
    } catch (Boom $exception) { // E?: later-invoked callback cannot throw here
        echo $exception->getMessage();
    }

    try {
        runNow(static function (): void {
            throw new Boom('now');
        });
    } catch (Boom $exception) { // V: default immediately-invoked, catch is live
        echo $exception->getMessage();
    }
}
