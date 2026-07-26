<?php

declare(strict_types=1);

namespace Conformance\Expectation;

final readonly class ExpectationEvaluation
{
    public function __construct(
        public string $errorsDiff,
        public string $conformanceAutomated,
        /** Null for tests without `// T` markers, i.e. plain soundness tests. */
        public ?TypeHandling $typeHandling = null,
    ) {
    }
}
