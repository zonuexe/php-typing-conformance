<?php

declare(strict_types=1);

namespace Conformance\Lsp;

use Internal\Toml\Toml;
use RuntimeException;

/**
 * The real-project navigation ground truth from lsp/navigation.toml.
 *
 * Unlike the fixture probes, positions here are line-pinned: the corpus is an
 * external checkout pinned to one commit, so line numbers are stable ground
 * truth, and only the column is derived (from a needle searched within that
 * one line). load() verifies the checkout is at the recorded commit and
 * throws when it is not, because every expectation below would silently rot.
 *
 * The corpus root may contain `~` and is overridable with the
 * LSP_NAV_CORPUS environment variable; returns null from tryLoad() when the
 * corpus is not present on this machine, so the rest of the probe run still
 * works on a machine without the survey checkout.
 */
final class NavigationDefinitions
{
    /**
     * @param list<array<string, mixed>> $symbols
     */
    private function __construct(
        public readonly string $root,
        public readonly string $commit,
        public readonly string $project,
        public readonly array $symbols,
    ) {
    }

    public static function tryLoad(string $navigationFile): ?self
    {
        if (!is_file($navigationFile)) {
            return null;
        }

        $data = Toml::parseToArray((string) file_get_contents($navigationFile));
        $root = (string) $data['root'];
        $override = getenv('LSP_NAV_CORPUS');
        if (is_string($override) && $override !== '') {
            $root = $override;
        }
        if (str_starts_with($root, '~/')) {
            $root = (string) getenv('HOME') . substr($root, 1);
        }
        $resolved = realpath($root);
        if ($resolved === false) {
            return null;
        }

        $commit = (string) $data['commit'];
        $head = trim((string) shell_exec(sprintf('git -C %s rev-parse HEAD 2>/dev/null', escapeshellarg($resolved))));
        if ($head !== '' && $head !== $commit) {
            throw new RuntimeException(
                "Navigation corpus {$resolved} is at {$head}, but navigation.toml pins {$commit}; " .
                'line-pinned expectations would be measured against the wrong source.',
            );
        }

        $symbols = [];
        foreach ($data['symbol'] ?? [] as $symbol) {
            $symbol['decl'] = self::position($resolved, $symbol['decl'], (string) $symbol['id']);
            // `probe` — the use site go-to-definition jumps FROM — is optional.
            // A symbol whose definition answer cannot be graded meaningfully
            // declares references alone; see the constructor symbol in
            // navigation.toml for the case that motivated it.
            $symbol['probe'] = isset($symbol['probe'])
                ? self::position($resolved, $symbol['probe'], (string) $symbol['id'])
                : null;
            $symbols[] = $symbol;
        }

        return new self($resolved, $commit, (string) ($data['project'] ?? ''), $symbols);
    }

    /**
     * Resolve {file, line, at} to {file, line, character}: the column is
     * where the needle first occurs within that one line.
     *
     * @param array<string, mixed> $position
     * @return array{file: string, line: int, character: int}
     */
    private static function position(string $root, array $position, string $id): array
    {
        $file = (string) $position['file'];
        $line = (int) $position['line'];
        $lines = file($root . '/' . $file);
        if ($lines === false || !isset($lines[$line - 1])) {
            throw new RuntimeException("Symbol {$id}: {$file}:{$line} does not exist in the corpus");
        }

        $column = strpos($lines[$line - 1], (string) $position['at']);
        if ($column === false) {
            throw new RuntimeException("Symbol {$id}: '{$position['at']}' not found at {$file}:{$line}");
        }

        return ['file' => $file, 'line' => $line, 'character' => $column];
    }
}
