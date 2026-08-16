<?php

declare(strict_types=1);

use Conformance\Metadata\AnalyzerCatalog;
use Conformance\Metadata\LanguageServerCatalog;
use Conformance\Metadata\Release;
use Conformance\Metadata\ReleaseTable;
use Conformance\Metadata\UpstreamReleases;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Check every tracked tool against its own release feed, and optionally
 * install what is newer.
 *
 *     php conformance/src/update-tools.php            # report only
 *     php conformance/src/update-tools.php --apply    # install, then record
 *
 * Four versions are in play and they answer different questions:
 *
 * - installed: what vendor-bin has, and therefore what a run would measure.
 * - installable: what the package manager would put there now. Composer
 *   resolves this against the platform, so it can sit below upstream's newest
 *   -- a release that wants a PHP this machine does not have is not a release
 *   this suite can measure, and saying so is the point of the column.
 * - recorded: what data/releases.toml says upstream's newest is, which is what
 *   the report's "Latest release" column shows.
 * - upstream: what the release feed says now.
 *
 * --apply installs the newest release of everything installable and rewrites
 * data/releases.toml. It deliberately does not re-run the analyzers: that is a
 * separate, slower step, and the results only change when someone runs it.
 */

const ANALYZER_SECTION = '# Analyzers.';
const LANGUAGE_SERVER_SECTION = '# Language servers.';

/**
 * How this repository installs each tool, if it does.
 *
 * `bin` is a vendor-bin namespace: a Composer one is updated by requiring the
 * newest release into it, an npm one by installing the newest tag. Tools with
 * no entry are read about but not installed here -- pzoom is built from a
 * local checkout, and phpantom and php-lsp ship only as per-platform GitHub
 * release binaries (installed by `make install-phpantom` / `make
 * install-php-lsp`, which this script cannot version-track). devsense-php-ls
 * has an entry for the opposite reason: it is installed purely to be probed
 * over LSP. phpactor was installed for that reason too until its `worse:analyse`
 * command became a matrix column; one install now serves both axes.
 *
 * qodana is the one entry that is measured without being installable: its
 * licence rules out shipping the linter, so its column comes from a PhpStorm
 * report produced by hand. A new release still shows up here, but acting on
 * it means updating the IDE and re-running Inspect Code.
 */
const INSTALLS = [
    'phan' => ['composer', 'phan', 'phan/phan'],
    'psalm' => ['composer', 'psalm', 'vimeo/psalm'],
    'phpstan' => ['composer', 'phpstan', 'phpstan/phpstan'],
    'intelephense' => ['npm', 'intelephense', 'intelephense'],
    'noverify' => ['composer', 'noverify', 'vkcom/noverify'],
    'mago' => ['composer', 'mago', 'carthage-software/mago'],
    'phpy' => ['npm', 'phpy', 'phpy'],
    'mir' => ['composer', 'mir', 'miropen/mir-php'],
    'steins' => ['composer', 'steins', 'typedduck/steins'],
    'phpactor' => ['composer', 'phpactor', 'phpactor/phpactor'],
    'devsense-php-ls' => ['npm', 'devsense-php-ls', 'devsense-php-ls'],
];

$rootDir = dirname(__DIR__);
$projectRoot = dirname($rootDir);
$releasesFile = $rootDir . '/data/releases.toml';
$apply = in_array('--apply', $argv, true);

$releases = ReleaseTable::fromTomlFile($releasesFile);
$analyzers = AnalyzerCatalog::build($releases)->all();
$servers = LanguageServerCatalog::build($releases)->all();
$upstream = new UpstreamReleases();

/** @var array<string, Release> $latest keyed by tool */
$latest = [];
$rows = [];

foreach ([...$analyzers, ...$servers] as $tool) {
    // Intelephense and Psalm are one artifact in two tables; ask once.
    if (isset($latest[$tool->tool]) || array_key_exists($tool->tool, $latest)) {
        continue;
    }

    $feed = $tool->releaseFeed();
    $found = $feed === null ? null : $upstream->latest($feed);
    $latest[$tool->tool] = $found ?? $tool->latestRelease;
    $failure = $found === null ? $upstream->failure() : null;

    $rows[] = [
        'tool' => $tool->tool,
        'installed' => installedVersion($projectRoot, $tool->tool),
        'installable' => installableVersion($projectRoot, $tool->tool, $found?->version),
        'recorded' => $tool->latestRelease->version,
        'upstream' => $found?->version,
        'date' => $found?->date,
        'feed' => $feed?->label() ?? '',
        'failure' => $failure,
    ];
}

printf("%-16s %-13s %-13s %-13s %-13s %s\n", 'TOOL', 'INSTALLED', 'INSTALLABLE', 'RECORDED', 'UPSTREAM', 'STATUS');

$outdated = [];

foreach ($rows as $row) {
    $status = status($row, $outdated);

    printf(
        "%-16s %-13s %-13s %-13s %-13s %s\n",
        $row['tool'],
        $row['installed'] ?? '-',
        $row['installable'] ?? '-',
        $row['recorded'],
        $row['upstream'] ?? '(unavailable)',
        $status,
    );
}

if (!$apply) {
    printf(
        "\n%s\nRun with --apply to install them and record the new releases.\n",
        $outdated === [] ? 'Everything is up to date.' : sprintf('%d tool(s) behind.', count($outdated)),
    );

    return;
}

foreach ($outdated as $tool => $version) {
    if (!isset(INSTALLS[$tool])) {
        printf("\n%s: %s is out, but this suite does not install it here.\n", $tool, $version);
        continue;
    }

    [$manager, $namespace, $package] = INSTALLS[$tool];

    $command = $manager === 'composer'
        ? sprintf(
            'composer bin %s require --dev --no-interaction --no-progress %s:^%s',
            escapeshellarg($namespace),
            escapeshellarg($package),
            $version,
        )
        : sprintf(
            'npm install --silent --prefix %s %s@%s',
            escapeshellarg('vendor-bin/' . $namespace),
            escapeshellarg($package),
            escapeshellarg($version),
        );

    printf("\n%s -> %s\n  %s\n", $tool, $version, $command);

    exec(sprintf('cd %s && %s 2>&1', escapeshellarg($projectRoot), $command), $output, $exitCode);

    if ($exitCode !== 0) {
        printf("  failed (exit %d):\n    %s\n", $exitCode, implode("\n    ", array_slice($output, -8)));
    }

    $output = [];
}

writeReleases($releasesFile, $analyzers, $servers, $latest);

printf(
    "\nRecorded the current releases in %s.\nRe-run the analyzers to measure the new versions: php conformance/src/main.php\n",
    $releasesFile,
);

/**
 * What vendor-bin actually has, which is what a run would measure.
 */
function installedVersion(string $projectRoot, string $tool): ?string
{
    if (!isset(INSTALLS[$tool])) {
        return null;
    }

    [$manager, $namespace, $package] = INSTALLS[$tool];

    if ($manager === 'composer') {
        $lock = sprintf('%s/vendor-bin/%s/composer.lock', $projectRoot, $namespace);

        if (!is_file($lock)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($lock), true);
        $packages = [...($data['packages'] ?? []), ...($data['packages-dev'] ?? [])];

        foreach ($packages as $installed) {
            if (($installed['name'] ?? null) === $package) {
                return ltrim((string) $installed['version'], 'vV');
            }
        }

        return null;
    }

    $manifest = sprintf('%s/vendor-bin/%s/node_modules/%s/package.json', $projectRoot, $namespace, $package);

    if (!is_file($manifest)) {
        return null;
    }

    $data = json_decode((string) file_get_contents($manifest), true);

    return isset($data['version']) ? (string) $data['version'] : null;
}

/**
 * What the package manager would install right now.
 *
 * Composer is asked, because it is the one that can answer below upstream:
 * `outdated` resolves the newest version whose requirements this platform
 * satisfies. npm does not enforce a package's `engines`, so for npm tools the
 * newest published release is also the installable one and upstream's answer
 * stands.
 */
function installableVersion(string $projectRoot, string $tool, ?string $upstream): ?string
{
    if (!isset(INSTALLS[$tool])) {
        return null;
    }

    [$manager, $namespace, $package] = INSTALLS[$tool];

    if ($manager !== 'composer') {
        return $upstream;
    }

    exec(
        sprintf(
            'composer outdated --all --format=json --working-dir=%s 2>/dev/null',
            escapeshellarg($projectRoot . '/vendor-bin/' . $namespace),
        ),
        $output,
        $exitCode,
    );

    if ($exitCode !== 0) {
        return null;
    }

    $data = json_decode(implode("\n", $output), true);

    foreach ($data['installed'] ?? [] as $installed) {
        if (($installed['name'] ?? null) === $package) {
            $latest = $installed['latest'] ?? null;

            return is_string($latest) ? ltrim($latest, 'vV') : null;
        }
    }

    return null;
}

/**
 * @param array{tool: string, installed: string|null, installable: string|null, recorded: string, upstream: string|null, date: string|null, feed: string, failure: string|null} $row
 * @param array<string, string> $outdated collects tool => version to install
 */
function status(array $row, array &$outdated): string
{
    if ($row['upstream'] === null) {
        if ($row['feed'] === '') {
            return 'no feed';
        }

        return sprintf('feed unavailable (%s)', $row['failure'] ?? 'no answer');
    }

    $notes = [];
    $installable = $row['installable'];

    if ($installable !== null && $row['installed'] !== null && version_compare($row['installed'], $installable, '<')) {
        $outdated[$row['tool']] = $installable;
        $notes[] = 'update available';
    }

    // Upstream released something this machine cannot install -- a platform
    // requirement, usually. Worth stating: the matrix will keep measuring the
    // older release while the table names the newer one.
    if ($installable !== null && $installable !== $row['upstream']) {
        $notes[] = sprintf('upstream %s not installable here', $row['upstream']);
    }

    if ($row['recorded'] !== $row['upstream']) {
        $notes[] = 'record stale';
    }

    return $notes === [] ? 'up to date' : implode(', ', $notes);
}

/**
 * Rewrite the release table, keeping its header and its two sections.
 *
 * @param list<\Conformance\Metadata\AnalyzerMetadata> $analyzers
 * @param list<\Conformance\Metadata\LanguageServerMetadata> $servers
 * @param array<string, Release> $latest
 */
function writeReleases(string $path, array $analyzers, array $servers, array $latest): void
{
    $contents = (string) file_get_contents($path);
    // Everything above the first entry is prose about the file; keep it.
    $header = substr($contents, 0, (int) strpos($contents, ANALYZER_SECTION));

    $sections = [ANALYZER_SECTION => $analyzers, LANGUAGE_SERVER_SECTION => $servers];
    $written = [];
    $out = rtrim($header) . "\n";

    foreach ($sections as $title => $tools) {
        $out .= "\n" . $title . "\n";

        foreach ($tools as $tool) {
            // Intelephense and Psalm are one artifact in two tables, one entry.
            if (isset($written[$tool->tool])) {
                continue;
            }

            $written[$tool->tool] = true;
            $release = $latest[$tool->tool];
            $out .= sprintf(
                "\n[%s]\nversion = \"%s\"\ndate = \"%s\"\n",
                $tool->tool,
                $release->version,
                $release->date,
            );
        }
    }

    if (file_put_contents($path, $out) === false) {
        throw new RuntimeException(sprintf('Failed to write the release table: %s', $path));
    }
}
