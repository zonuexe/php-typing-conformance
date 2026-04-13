<?php

declare(strict_types=1);

namespace Conformance\Expectation;

final readonly class ExpectedDiagnostic
{
    public function __construct(
        public int $line,
        public bool $required,
        public ?string $tag,
        public bool $allowMultiple,
        public string $comment,
    ) {
    }
}
