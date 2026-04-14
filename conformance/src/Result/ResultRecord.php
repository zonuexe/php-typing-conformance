<?php

declare(strict_types=1);

namespace Conformance\Result;

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
        public ?int $firstDetectedLevel,
        public string $output,
        public string $errorsDiff,
        public string $notes,
        public array $ignoreErrors,
        public int $expectedDiagnosticCount,
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

        if ($this->firstDetectedLevel !== null) {
            $payload['first_detected_level'] = $this->firstDetectedLevel;
        }

        return $payload;
    }
}
