<?php

declare(strict_types=1);

namespace Conformance\Discovery;

use RuntimeException;

final class TestCaseDiscovery
{
    /**
     * @param array<string, mixed> $testGroups
     * @return list<TestCase>
     */
    public function discover(string $testsDir, array $testGroups): array
    {
        if (!is_dir($testsDir)) {
            throw new RuntimeException(sprintf('Tests directory not found: %s', $testsDir));
        }

        $groupKeys = array_keys($testGroups);
        $entries = scandir($testsDir);
        if ($entries === false) {
            throw new RuntimeException(sprintf('Failed to read tests directory: %s', $testsDir));
        }

        $testCases = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!str_ends_with($entry, '.php')) {
                continue;
            }

            if (str_starts_with($entry, '_')) {
                continue;
            }

            $groupKey = $this->detectGroupKey($entry, $groupKeys);
            if ($groupKey === null) {
                continue;
            }

            $path = $testsDir . DIRECTORY_SEPARATOR . $entry;
            $testCases[] = new TestCase(
                path: $path,
                fileName: $entry,
                name: pathinfo($entry, PATHINFO_FILENAME),
                groupKey: $groupKey,
            );
        }

        usort(
            $testCases,
            static fn (TestCase $left, TestCase $right): int => $left->fileName <=> $right->fileName,
        );

        return $testCases;
    }

    /**
     * @param list<string> $groupKeys
     */
    private function detectGroupKey(string $fileName, array $groupKeys): ?string
    {
        foreach ($groupKeys as $groupKey) {
            if (str_starts_with($fileName, $groupKey . '_')) {
                return $groupKey;
            }
        }

        return null;
    }
}
