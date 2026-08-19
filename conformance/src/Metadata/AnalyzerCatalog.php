<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use Conformance\Metadata\Analyzer\Intelephense;
use Conformance\Metadata\Analyzer\Mago;
use Conformance\Metadata\Analyzer\Mir;
use Conformance\Metadata\Analyzer\NoVerify;
use Conformance\Metadata\Analyzer\Phan;
use Conformance\Metadata\Analyzer\Phpactor;
use Conformance\Metadata\Analyzer\Phpantom;
use Conformance\Metadata\Analyzer\Phpy;
use Conformance\Metadata\Analyzer\PhpStan;
use Conformance\Metadata\Analyzer\Psalm;
use Conformance\Metadata\Analyzer\Pzoom;
use Conformance\Metadata\Analyzer\Qodana;
use Conformance\Metadata\Analyzer\SonarQube;
use Conformance\Metadata\Analyzer\Steins;
use RuntimeException;
use function sprintf;

/**
 * Every analyzer the report knows about, in the order the table shows them,
 * and reachable by the tool name the rest of the runner uses.
 */
final class AnalyzerCatalog
{
    /**
     * Ordered by initial release, oldest first. The keys are the tool names
     * the runner already uses for its result directories, so one vocabulary
     * covers both halves of the report.
     *
     * @var array<string, class-string<AnalyzerMetadata>>
     */
    private const ANALYZERS = [
        'sonarqube' => SonarQube::class,
        'phan' => Phan::class,
        'psalm' => Psalm::class,
        'phpstan' => PhpStan::class,
        'intelephense' => Intelephense::class,
        'phpactor' => Phpactor::class,
        'noverify' => NoVerify::class,
        'qodana' => Qodana::class,
        'mago' => Mago::class,
        'phpy' => Phpy::class,
        'phpantom' => Phpantom::class,
        'mir' => Mir::class,
        'pzoom' => Pzoom::class,
        'steins' => Steins::class,
    ];

    /**
     * Result directories that are one analyzer under another configuration,
     * not an analyzer of their own: the same binary, so the same version
     * banner and the same releases.
     *
     * psalm-next is a second, separately installed Psalm — the 7.x line,
     * currently 7.0.0-beta19 — so it has its own version banner, but it is
     * still the same project: the reference table and the release table
     * describe Psalm once, and the matrix column carries the next line.
     *
     * @var array<string, string>
     */
    private const CONFIGURATIONS = [
        'phpstan-strict' => 'phpstan',
        'psalm-next' => 'psalm',
    ];

    /** @var array<string, AnalyzerMetadata> */
    private readonly array $byTool;

    /**
     * @param list<AnalyzerMetadata> $analyzers
     * @param array<string, string> $versionBanners raw version banners, keyed by tool
     */
    public function __construct(
        private readonly array $analyzers,
        private readonly array $versionBanners = [],
    ) {
        $byTool = [];

        foreach ($analyzers as $analyzer) {
            $byTool[$analyzer->tool] = $analyzer;
        }

        $this->byTool = $byTool;
    }

    /**
     * Pair each analyzer's curated facts with its current release.
     *
     * @param array<string, Release> $releases keyed by tool name; see [[ReleaseTable]]
     * @param array<string, string> $versionBanners the raw `--version` output
     *        recorded per results/<tool>/version.toml, keyed by tool name —
     *        the version this suite evaluated. Absent where nothing has been
     *        measured yet (update-tools.php builds without results).
     */
    public static function build(array $releases, array $versionBanners = []): self
    {
        $analyzers = [];

        foreach (self::ANALYZERS as $tool => $class) {
            $release = $releases[$tool] ?? throw new RuntimeException(
                sprintf('No release recorded for analyzer: %s', $tool),
            );

            $analyzers[] = new $class($tool, $release, $versionBanners[$tool] ?? null);
        }

        return new self($analyzers, $versionBanners);
    }

    /**
     * @return list<AnalyzerMetadata> in table order
     */
    public function all(): array
    {
        return $this->analyzers;
    }

    /**
     * Null for a tool with no record — nothing in the report depends on one
     * existing, so an unknown column degrades to an unlinked version string
     * rather than failing the whole run.
     */
    public function find(string $tool): ?AnalyzerMetadata
    {
        $tool = self::CONFIGURATIONS[$tool] ?? $tool;

        return $this->byTool[$tool] ?? null;
    }

    /**
     * True for a result directory that is one analyzer under another
     * configuration (phpstan-strict, psalm-next) rather than a column of its
     * own. The index matrix does not give such a row a column; the detail
     * pages still show it.
     */
    public function isConfiguration(string $tool): bool
    {
        return isset(self::CONFIGURATIONS[$tool]);
    }

    /**
     * The configuration rows of one analyzer (psalm-next under psalm), for the
     * version cell to name the other line when it has one. Each entry carries
     * the configuration's own evaluated release; the base analyzer's version
     * pattern reads its banner, since a configuration is the same project.
     *
     * @return list<array{tool: string, release: Release}>
     */
    public function configurationsOf(string $tool): array
    {
        $base = $this->byTool[$tool] ?? null;
        $out = [];

        foreach (self::CONFIGURATIONS as $configTool => $baseTool) {
            if ($baseTool !== $tool || $base === null) {
                continue;
            }

            $banner = $this->versionBanners[$configTool] ?? null;
            if ($banner === null) {
                continue;
            }

            $out[] = ['tool' => $configTool, 'release' => new Release($base->shortVersion($banner))];
        }

        return $out;
    }
}
