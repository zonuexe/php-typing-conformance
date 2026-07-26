<?php

declare(strict_types=1);

namespace Conformance\Result;

use Internal\Toml\Toml;
use Throwable;
use RuntimeException;

final class ResultRepository
{
    public function __construct(
        private readonly string $resultsRoot,
    ) {
    }

    public function save(ResultRecord $record): string
    {
        $toolDir = $this->resultsRoot . '/' . $record->tool;
        if (!is_dir($toolDir) && !mkdir($toolDir, 0777, true) && !is_dir($toolDir)) {
            throw new RuntimeException(sprintf('Failed to create results directory: %s', $toolDir));
        }

        $path = "{$toolDir}/{$record->testName}.toml";
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

        $toml = $this->encodeResultPayload($payload);

        if (file_put_contents($path, $toml) === false) {
            throw new RuntimeException(sprintf('Failed to write result file: %s', $path));
        }

        return $path;
    }

    public function saveVersion(string $tool, string $version): string
    {
        $toolDir = $this->resultsRoot . '/' . $tool;
        if (!is_dir($toolDir) && !mkdir($toolDir, 0777, true) && !is_dir($toolDir)) {
            throw new RuntimeException(sprintf('Failed to create results directory: %s', $toolDir));
        }

        $path = $toolDir . '/version.toml';
        $toml = (string) Toml::encode(['version' => $version]);

        if (file_put_contents($path, $toml) === false) {
            throw new RuntimeException(sprintf('Failed to write version file: %s', $path));
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public function loadResult(string $tool, string $testName): array
    {
        $path = "{$this->resultsRoot}/{$tool}/{$testName}.toml";

        return $this->load($path);
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

        try {
            return Toml::parseToArray($contents);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeResultPayload(array $payload): string
    {
        $lines = [];

        foreach ($payload as $key => $value) {
            $lines[] = $this->encodeKeyValue($key, $value);
        }

        return implode("\n", $lines) . "\n";
    }

    private function encodeKeyValue(string $key, mixed $value): string
    {
        return sprintf('%s = %s', $key, $this->encodeValue($value));
    }

    private function encodeValue(mixed $value): string
    {
        if (is_string($value)) {
            return $this->encodeString($value);
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $items = array_map(fn (mixed $item): string => $this->encodeValue($item), $value);
            return '[' . implode(', ', $items) . ']';
        }

        throw new RuntimeException(sprintf('Unsupported value type: %s', get_debug_type($value)));
    }

    private function encodeString(string $value): string
    {
        if (str_contains($value, "\n")) {
            $escaped = str_replace(
                ["\\", '"""'],
                ["\\\\", '\"\"\"'],
                $value,
            );

            return "\"\"\"\n" . $escaped . "\"\"\"";
        }

        $escaped = addcslashes($value, "\\\"\n\r\t");

        return '"' . $escaped . '"';
    }
}
