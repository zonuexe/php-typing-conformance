<?php

declare(strict_types=1);

namespace Conformance\Lsp;

/**
 * How to launch and configure one language server for a probe run.
 *
 * The command receives the prepared workspace path via the {workspace}
 * placeholder, because servers disagree about where the project root comes
 * from: Psalm takes it as -r on the command line, everyone else reads the
 * rootUri from the initialize request and needs no placeholder at all.
 *
 * configFiles are copied into the workspace before launch — psalm.xml,
 * .phan/config.php — so each server finds its own configuration exactly
 * where its CLI sibling would. settings go the LSP way instead, via
 * didChangeConfiguration and workspace/configuration pulls.
 */
final class LspServer
{
    /**
     * @param list<string> $command argv, {workspace} resolved per run
     * @param array<string, mixed> $initializationOptions
     * @param array<string, mixed>|null $settings null when the server takes no settings
     * @param array<string, string> $configFiles workspace-relative path => absolute source path
     * @param list<string> $openExtra extra workspace-relative files to didOpen
     * @param list<string>|null $versionCommand argv printing the version, null when it comes from a package.json
     */
    public function __construct(
        public readonly string $tool,
        public readonly array $command,
        public readonly array $initializationOptions = [],
        public readonly ?array $settings = null,
        public readonly array $configFiles = [],
        public readonly array $openExtra = [],
        public readonly bool $skipNavigation = false,
        public readonly ?string $frameworkProbesFile = null,
        public readonly ?array $versionCommand = null,
        public readonly ?string $packageJsonPath = null,
    ) {
    }

    /** @return list<string> */
    public function commandFor(string $workspace): array
    {
        return array_map(
            static fn (string $part): string => str_replace('{workspace}', $workspace, $part),
            $this->command,
        );
    }

    /** The version a run should record, in the tool's own words. */
    public function version(): string
    {
        if ($this->packageJsonPath !== null) {
            $contents = @file_get_contents($this->packageJsonPath);
            $data = $contents === false ? null : json_decode($contents, true);

            return $this->tool . ' ' . (string) (is_array($data) ? ($data['version'] ?? 'unknown') : 'unknown');
        }

        if ($this->versionCommand === null) {
            return 'unknown';
        }

        $command = implode(' ', array_map(escapeshellarg(...), $this->versionCommand));
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || $output === []) {
            return 'unknown';
        }

        foreach ($output as $line) {
            $line = trim($line);
            if ($line !== '') {
                return $line;
            }
        }

        return 'unknown';
    }
}
