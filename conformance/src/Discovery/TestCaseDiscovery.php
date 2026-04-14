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
        $supportFiles = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!str_ends_with($entry, '.php')) {
                continue;
            }

            if (str_starts_with($entry, '_')) {
                $supportFiles[] = $entry;
                continue;
            }

            $groupKey = $this->detectGroupKey($entry, $groupKeys);
            if ($groupKey === null) {
                continue;
            }

            $path = $testsDir . DIRECTORY_SEPARATOR . $entry;
            $name = pathinfo($entry, PATHINFO_FILENAME);
            $testCases[] = new TestCase(
                path: $path,
                fileName: $entry,
                name: $name,
                groupKey: $groupKey,
                supportPaths: $this->resolveSupportPaths($testsDir, $name, $supportFiles),
            );
        }

        usort(
            $testCases,
            static fn (TestCase $left, TestCase $right): int => $left->fileName <=> $right->fileName,
        );

        return $testCases;
    }

    /**
     * @param list<string> $supportFiles
     * @return list<string>
     */
    private function resolveSupportPaths(string $testsDir, string $testName, array $supportFiles): array
    {
        $paths = [];
        $prefix = '_' . $testName;

        foreach ($supportFiles as $supportFile) {
            $supportName = pathinfo($supportFile, PATHINFO_FILENAME);
            if (!str_starts_with($supportName, $prefix)) {
                continue;
            }

            $paths[] = $testsDir . DIRECTORY_SEPARATOR . $supportFile;
        }

        sort($paths);

        return $paths;
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
