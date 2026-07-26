<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use Conformance\Metadata\Analyzer\Intelephense;
use Conformance\Metadata\Analyzer\Mago;
use Conformance\Metadata\Analyzer\Mir;
use Conformance\Metadata\Analyzer\NoVerify;
use Conformance\Metadata\Analyzer\Phan;
use Conformance\Metadata\Analyzer\Phpy;
use Conformance\Metadata\Analyzer\PhpStan;
use Conformance\Metadata\Analyzer\Psalm;
use Conformance\Metadata\Analyzer\Pzoom;
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
        'phan' => Phan::class,
        'psalm' => Psalm::class,
        'phpstan' => PhpStan::class,
        'intelephense' => Intelephense::class,
        'noverify' => NoVerify::class,
        'mago' => Mago::class,
        'phpy' => Phpy::class,
        'mir' => Mir::class,
        'pzoom' => Pzoom::class,
        'steins' => Steins::class,
    ];

    /**
     * Result directories that are one analyzer under another configuration,
     * not an analyzer of their own: the same binary, so the same version
     * banner and the same releases.
     *
     * @var array<string, string>
     */
    private const CONFIGURATIONS = [
        'phpstan-strict' => 'phpstan',
    ];

    /** @var array<string, AnalyzerMetadata> */
    private readonly array $byTool;

    /**
     * @param list<AnalyzerMetadata> $analyzers
     */
    public function __construct(private readonly array $analyzers)
    {
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
     */
    public static function build(array $releases): self
    {
        $analyzers = [];

        foreach (self::ANALYZERS as $tool => $class) {
            $release = $releases[$tool] ?? throw new RuntimeException(
                sprintf('No release recorded for analyzer: %s', $tool),
            );

            $analyzers[] = new $class($tool, $release);
        }

        return new self($analyzers);
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
}
