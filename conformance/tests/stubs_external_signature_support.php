<?php

declare(strict_types=1);

namespace Conformance\Tests\StubsExternalSignatureSupport;

/**
 * External signature support through companion helper files.
 *
 * References:
 * - python-typing distribution / external type information inspiration
 * - PHP analyzer support for separately supplied symbols
 */

final class ValidClock implements ExternalClock
{
    #[\Override]
    public function timestamp(): int
    {
        return 1700000000;
    }
}

final class InvalidClock
{
    public function time(): string
    {
        return 'now';
    }
}

function takesClock(ExternalClock $clock): void
{
}

takesClock(new ValidClock());
takesClock(new InvalidClock()); // E: object does not satisfy the companion external signature
