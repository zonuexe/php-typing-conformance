<?php

declare(strict_types=1);

namespace Conformance\Result;

use Conformance\Expectation\TypeHandling;

final readonly class ResultRecord
{
    /**
     * @param list<string> $ignoreErrors
     */
    public function __construct(
        public string $tool,
        public string $testName,
        public string $status,
        public string $conformanceAutomated,
        /**
         * Lowest PHPStan level whose rules report the diagnostic this test
         * expects. Null when the tool has no level concept, or when nothing on
         * an expected line is reported at all.
         */
        public ?int $expectedDiagnosticLevel,
        public string $output,
        public string $errorsDiff,
        public string $notes,
        public array $ignoreErrors,
        public int $expectedDiagnosticCount,
        /** Set only for `// T`-marked type-handling tests. */
        public ?TypeHandling $typeHandling = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'status' => $this->status,
            'conformance_automated' => $this->conformanceAutomated,
            'expected_diagnostic_count' => $this->expectedDiagnosticCount,
            'output' => $this->output,
            'errors_diff' => $this->errorsDiff,
            'notes' => $this->notes,
            'ignore_errors' => $this->ignoreErrors,
        ];

        if ($this->expectedDiagnosticLevel !== null) {
            $payload['expected_diagnostic_level'] = $this->expectedDiagnosticLevel;
        }

        if ($this->typeHandling !== null) {
            $payload['recognition'] = $this->typeHandling->recognition;
            $payload['enforcement'] = $this->typeHandling->enforcement;
            $payload['enforced_lines'] = sprintf(
                '%d/%d',
                $this->typeHandling->enforcedLineCount,
                $this->typeHandling->expectedLineCount,
            );
            $payload['unrecognized_lines'] = $this->typeHandling->unrecognizedLines;
            $payload['false_positive_lines'] = $this->typeHandling->falsePositiveLines;
            $payload['over_rejected_lines'] = $this->typeHandling->overRejectedLines;
        }

        return $payload;
    }
}
