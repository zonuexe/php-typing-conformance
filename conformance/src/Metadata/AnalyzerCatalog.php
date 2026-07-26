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
 * Every analyzer the report knows about, in the order the table shows them.
 *
 * The keys are the tool names the runner already uses for its result
 * directories, so one vocabulary covers both halves of the report.
 */
final class AnalyzerCatalog
{
    /**
     * Ordered by initial release, oldest first.
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
     * Pair each analyzer's curated facts with its current release.
     *
     * @param array<string, Release> $releases keyed by tool name; see [[ReleaseTable]]
     * @return list<AnalyzerMetadata>
     */
    public static function build(array $releases): array
    {
        $analyzers = [];

        foreach (self::ANALYZERS as $tool => $class) {
            $release = $releases[$tool] ?? throw new RuntimeException(
                sprintf('No release recorded for analyzer: %s', $tool),
            );

            $analyzers[] = new $class($release);
        }

        return $analyzers;
    }
}
