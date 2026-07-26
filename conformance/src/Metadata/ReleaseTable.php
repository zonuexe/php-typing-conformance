<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use Internal\Toml\Toml;
use RuntimeException;
use function is_array;
use function is_file;
use function sprintf;

/**
 * Load the latest upstream release of each analyzer.
 *
 * Kept in a data file rather than in the metadata classes because it is the
 * one part that goes stale without anybody changing their mind: a script can
 * rewrite the whole file from upstream release feeds without touching a line
 * of curated judgement.
 */
final class ReleaseTable
{
    /**
     * @return array<string, Release> keyed by tool name
     */
    public static function fromTomlFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Release table not found: %s', $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Failed to read release table: %s', $path));
        }

        $releases = [];

        foreach (Toml::parseToArray($contents) as $tool => $release) {
            if (!is_array($release) || !isset($release['version'], $release['date'])) {
                throw new RuntimeException(sprintf('Release entry for %s needs a version and a date', $tool));
            }

            $releases[(string) $tool] = new Release((string) $release['version'], (string) $release['date']);
        }

        return $releases;
    }
}
