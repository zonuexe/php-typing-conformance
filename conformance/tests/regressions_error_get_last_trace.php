<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsErrorGetLastTrace;

/**
 * PHP 8.5 adds an optional `trace` key to `error_get_last()`.
 *
 * `fatal_error_backtraces` is on by default. Fatal errors collected at shutdown
 * then carry a `debug_backtrace()`-shaped frame list under `trace`. The RFC
 * only populates the key for those fatals, so it is optional; the other four
 * keys (`type`, `message`, `file`, `line`) stay required.
 *
 * A tool whose stub is still the 8.4 shape reports `trace` as an unknown
 * offset (`InvalidArrayOffset`, "expecting file, line, message or type"). That
 * is the miss this file measures. Access goes through `??` so an honest
 * optional-key reading does not also have to warn about a missing offset.
 *
 * Source: vimeo/psalm#11975, phpstan/phpstan-src#6470. Symfony hit the sealed
 * 8.4 shape in symfony/symfony#66145.
 *
 * References:
 * - https://wiki.php.net/rfc/error_backtraces_v2
 * - https://github.com/vimeo/psalm/pull/11975
 * - https://github.com/phpstan/phpstan-src/pull/6470
 */

function takesInt(int $value): void
{
}

function takesString(string $value): void
{
}

function takesMixed(mixed $value): void
{
}

function inspectLastError(): void
{
    $error = error_get_last();
    if ($error === null) {
        return;
    }

    takesMixed($error['type']); // V: required 8.4 key
    takesMixed($error['message']); // V
    takesMixed($error['file']); // V
    takesMixed($error['line']); // V
    takesMixed($error['trace'] ?? null); // Q: PHP 8.5 optional key — must not be an unknown offset
    takesMixed($error['nope']); // E?: unknown key — tools with an unsealed bag stay silent
}

inspectLastError();
