<?php

declare(strict_types=1);

namespace Conformance\Result;

use Internal\Toml\Toml;
use RuntimeException;

final class ResultRepository
{
    public function __construct(
        private readonly string $resultsRoot,
    ) {
    }

    public function save(ResultRecord $record): string
    {
        $toolDir = $this->resultsRoot . DIRECTORY_SEPARATOR . $record->tool;
        if (!is_dir($toolDir) && !mkdir($toolDir, 0777, true) && !is_dir($toolDir)) {
            throw new RuntimeException(sprintf('Failed to create results directory: %s', $toolDir));
        }

        $path = $toolDir . DIRECTORY_SEPARATOR . $record->testName . '.toml';
        $existing = $this->load($path);
        $payload = $record->toArray();

        if (isset($existing['status']) && is_string($existing['status']) && $existing['status'] !== '') {
            $payload['status'] = $existing['status'];
        }

        if (isset($existing['notes']) && is_string($existing['notes']) && $existing['notes'] !== '') {
            $payload['notes'] = $existing['notes'];
        }

        if (isset($existing['ignore_errors']) && is_array($existing['ignore_errors'])) {
            $payload['ignore_errors'] = array_values(
                array_map(static fn (mixed $value): string => (string) $value, $existing['ignore_errors']),
            );
        }

        $toml = (string) Toml::encode($payload);

        if (file_put_contents($path, $toml) === false) {
            throw new RuntimeException(sprintf('Failed to write result file: %s', $path));
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read result file: %s', $path));
        }

        return Toml::parseToArray($contents);
    }
}
