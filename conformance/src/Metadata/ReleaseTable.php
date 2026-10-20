<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use Internal\Toml\Toml;
use RuntimeException;
use function array_diff_key;
use function array_keys;
use function implode;
use function is_array;
use function is_file;
use function preg_match;
use function preg_split;
use function sprintf;

/**
 * Load the latest upstream release of each analyzer.
 *
 * Kept in a data file rather than in the metadata classes because it is the
 * one part that goes stale without anybody changing their mind: a script can
 * refresh it from upstream release feeds without touching a line of curated
 * judgement.
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

    /**
     * Put new releases into the file's text, changing nothing else.
     *
     * Only the `version` and `date` values of the named tables are replaced.
     * Comments, blank lines, table order and which section a table sits in are
     * hand-written, so they come through byte for byte: regenerating the file
     * from the catalogs used to drop every comment below the header.
     *
     * A tool with no table yet is refused rather than appended, because where
     * its entry belongs, and what to say about it, is an editorial choice.
     *
     * @param array<string, Release> $releases keyed by tool name
     */
    public static function rewrite(string $contents, array $releases): string
    {
        $lines = preg_split('/(?<=\n)/', $contents);
        if ($lines === false) {
            throw new RuntimeException('Failed to split the release table');
        }

        $table = null;
        $seen = [];

        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*\[([^\]]+)\]\s*$/', $line, $match) === 1) {
                $table = $match[1];
                $seen[$table] = true;
                continue;
            }

            if ($table === null || !isset($releases[$table])) {
                continue;
            }

            if (preg_match('/^(\s*(version|date)\s*=\s*)"[^"]*"(.*?)(\r?\n)?$/', $line, $match) === 1) {
                $value = $match[2] === 'version' ? $releases[$table]->version : $releases[$table]->date;
                $lines[$i] = $match[1] . '"' . $value . '"' . $match[3] . ($match[4] ?? '');
            }
        }

        $missing = array_diff_key($releases, $seen);
        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'Release table has no entry for %s; add one by hand in the right section',
                implode(', ', array_keys($missing)),
            ));
        }

        $rewritten = implode('', $lines);

        foreach (Toml::parseToArray($rewritten) as $tool => $release) {
            $expected = $releases[(string) $tool] ?? null;
            if ($expected !== null && (!is_array($release)
                || ($release['version'] ?? null) !== $expected->version
                || ($release['date'] ?? null) !== $expected->date)) {
                throw new RuntimeException(sprintf('Release entry for %s did not take the new values', $tool));
            }
        }

        return $rewritten;
    }
}
