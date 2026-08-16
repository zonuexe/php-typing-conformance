<?php

declare(strict_types=1);

namespace Conformance\Lsp;

use Internal\Toml\Toml;
use RuntimeException;

/**
 * The Gate imageboard checkout used as Laravel LSP's real-project workspace.
 *
 * Same pinning rule as NavigationDefinitions: the TOML records a commit, and
 * load throws when the submodule is at a different HEAD, because every
 * framework probe is anchored to source that would otherwise silently move.
 * tryLoad() returns null when the submodule is not present, so a machine
 * that skipped `make init-submodules` still gets the stub-artisan session.
 */
final class LaravelCorpus
{
    private function __construct(
        public readonly string $root,
        public readonly string $commit,
        public readonly string $project,
        public readonly bool $hasVendor,
    ) {
    }

    public static function tryLoad(string $corpusFile, string $projectRoot): ?self
    {
        if (!is_file($corpusFile)) {
            return null;
        }

        $data = Toml::parseToArray((string) file_get_contents($corpusFile));
        $root = (string) $data['root'];
        $override = getenv('LSP_LARAVEL_CORPUS');
        if (is_string($override) && $override !== '') {
            $root = $override;
        }
        if (!str_starts_with($root, '/')) {
            $root = $projectRoot . '/' . $root;
        }

        $resolved = realpath($root);
        if ($resolved === false || !is_file($resolved . '/artisan')) {
            return null;
        }

        $commit = (string) $data['commit'];
        $head = trim((string) shell_exec(sprintf('git -C %s rev-parse HEAD 2>/dev/null', escapeshellarg($resolved))));
        if ($head !== '' && $head !== $commit) {
            throw new RuntimeException(
                "Laravel corpus {$resolved} is at {$head}, but corpus.toml pins {$commit}; " .
                'framework probes would be measured against the wrong source.',
            );
        }

        return new self(
            $resolved,
            $commit,
            (string) ($data['project'] ?? ''),
            is_file($resolved . '/vendor/autoload.php'),
        );
    }
}
