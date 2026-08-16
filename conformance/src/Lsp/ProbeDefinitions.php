<?php

declare(strict_types=1);

namespace Conformance\Lsp;

use Internal\Toml\Toml;
use RuntimeException;

/**
 * The probe list from probes.toml with every text anchor resolved to a
 * line/character position against the fixture files.
 *
 * Anchors resolve at load time, once, against the fixtures as committed —
 * not against the workspace copies, which are byte-identical anyway. A probe
 * whose anchor no longer occurs is a broken suite, not a missing feature, so
 * resolution failure throws instead of skipping.
 *
 * Lines are 1-based here and in the client spec (the client subtracts one on
 * the wire, where LSP counts from zero); characters are 0-based UTF-8 byte
 * offsets within the line, which coincides with LSP's UTF-16 code units for
 * these all-ASCII fixtures.
 */
final class ProbeDefinitions
{
    /**
     * @param list<array<string, mixed>> $capabilityProbes client-ready probe entries
     * @param list<array<string, mixed>> $hoverProbes client-ready, plus feature/expected/precise/reject kept for grading
     */
    private function __construct(
        public readonly array $capabilityProbes,
        public readonly array $hoverProbes,
    ) {
    }

    public static function load(string $probesFile, string $fixturesDir): self
    {
        $contents = file_get_contents($probesFile);
        if ($contents === false) {
            throw new RuntimeException("Cannot read {$probesFile}");
        }

        $data = Toml::parseToArray($contents);
        $capabilities = [];
        $hovers = [];

        foreach ($data['capability'] ?? [] as $entry) {
            $capabilities[] = self::resolve($entry, $fixturesDir);
        }

        foreach ($data['hover'] ?? [] as $entry) {
            $entry['method'] = 'textDocument/hover';
            $entry['id'] = 'hover:' . (string) $entry['id'];
            $hovers[] = self::resolve($entry, $fixturesDir);
        }

        return new self($capabilities, $hovers);
    }

    /**
     * Framework-specific probes (Laravel env/route/view, …) resolved against
     * their own fixture directory. Same shape as a capability probe, plus
     * an optional `expected` string used when grading hover text.
     *
     * @return list<array<string, mixed>>
     */
    public static function loadFramework(string $probesFile, string $fixturesDir): array
    {
        $contents = file_get_contents($probesFile);
        if ($contents === false) {
            throw new RuntimeException("Cannot read {$probesFile}");
        }

        $data = Toml::parseToArray($contents);
        $probes = [];
        foreach ($data['framework'] ?? [] as $entry) {
            $probes[] = self::resolve($entry, $fixturesDir);
        }

        return $probes;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function resolve(array $entry, string $fixturesDir): array
    {
        $anchor = $entry['at'] ?? null;
        if ($anchor === null) {
            return $entry;
        }

        $file = (string) $entry['file'];
        $source = file_get_contents($fixturesDir . '/' . $file);
        if ($source === false) {
            throw new RuntimeException("Probe {$entry['id']}: fixture {$file} not found");
        }

        $index = strpos($source, (string) $anchor);
        if ($index === false) {
            throw new RuntimeException("Probe {$entry['id']}: anchor not found in {$file}: {$anchor}");
        }

        $index += (int) ($entry['offset'] ?? 0);
        $before = substr($source, 0, $index);
        $entry['line'] = substr_count($before, "\n") + 1;
        $lastNewline = strrpos($before, "\n");
        $entry['character'] = $lastNewline === false ? $index : $index - $lastNewline - 1;
        unset($entry['at'], $entry['offset']);

        return $entry;
    }
}
