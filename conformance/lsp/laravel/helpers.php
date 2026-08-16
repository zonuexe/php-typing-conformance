<?php

declare(strict_types=1);

namespace Conformance\LspLaravel;

/**
 * Laravel-shaped probes that do not need a booted framework: the env
 * provider reads `.env` from disk. Hover, definition, completion and the
 * missing-key diagnostic all hang off these three calls.
 */

function usesKnownEnv(): string
{
    return env('APP_NAME');
}

function usesMissingEnv(): string
{
    return env('MISSING_KEY');
}

function completesEnv(): string
{
    return env('');
}
