<?php

declare(strict_types=1);

namespace Conformance\Expectation;

final readonly class ExpectedDiagnostic
{
    public function __construct(
        public int $line,
        public bool $required,
        public ?string $tool,
        public ?string $tag,
        public bool $allowMultiple,
        public string $comment,
        /**
         * Quiet probe (`// Q` / `// Q?`): success is *silence* on this line.
         * Used for suppress / ignore tags (e.g. `@psalm-ignore-falsable-return`)
         * where honouring the feature means not reporting, not reporting.
         */
        public bool $quiet = false,
        /**
         * Valid-control probe (`// V`): this line must stay silent for
         * enforcement to be genuine. A type-rejection here means the analyzer
         * also rejects values the spelling admits — incidental / over-rejection,
         * not enforcement. Distinct from `quiet`, which *counts* silence as
         * honouring a suppress tag.
         */
        public bool $valid = false,
    ) {
    }
}
