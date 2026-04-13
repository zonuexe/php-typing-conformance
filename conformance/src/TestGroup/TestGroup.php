<?php

declare(strict_types=1);

namespace Conformance\TestGroup;

final readonly class TestGroup
{
    /**
     * @param list<string> $references
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $sourceCategory,
        public string $description,
        public array $references,
    ) {
    }
}
