<?php

declare(strict_types=1);

namespace Conformance\Discovery;

final readonly class TestCase
{
    public function __construct(
        public string $path,
        public string $fileName,
        public string $name,
        public string $groupKey,
    ) {
    }
}
