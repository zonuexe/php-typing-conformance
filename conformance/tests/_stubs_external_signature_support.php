<?php

declare(strict_types=1);

namespace Conformance\Tests\StubsExternalSignatureSupport;

interface ExternalClock
{
    public function timestamp(): int;
}
