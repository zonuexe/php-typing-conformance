<?php

declare(strict_types=1);

namespace Conformance\TestGroup;

use Internal\Toml\Toml;
use RuntimeException;

final class TestGroupLoader
{
    /**
     * @return array<string, TestGroup>
     */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Test group file not found: %s', $path));
        }

        $data = Toml::parseToArray((string) file_get_contents($path));
        $groups = [];

        foreach ($data as $key => $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException(sprintf('Invalid test group definition for "%s"', $key));
            }

            $references = $definition['references'] ?? [];
            if (!is_array($references)) {
                throw new RuntimeException(sprintf('Invalid references for "%s"', $key));
            }

            $groups[$key] = new TestGroup(
                key: (string) $key,
                name: (string) ($definition['name'] ?? $key),
                sourceCategory: (string) ($definition['source_category'] ?? 'unknown'),
                description: (string) ($definition['description'] ?? ''),
                references: array_values(array_map(static fn (mixed $value): string => (string) $value, $references)),
            );
        }

        return $groups;
    }
}
