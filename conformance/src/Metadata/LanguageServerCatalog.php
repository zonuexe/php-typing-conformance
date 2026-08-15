<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use Conformance\Metadata\LanguageServer\DevsensePhpLs;
use Conformance\Metadata\LanguageServer\Intelephense;
use Conformance\Metadata\LanguageServer\Phpactor;
use Conformance\Metadata\LanguageServer\Phpantom;
use Conformance\Metadata\LanguageServer\PhpLsp;
use Conformance\Metadata\LanguageServer\PhpUnit;
use Conformance\Metadata\LanguageServer\Psalm;
use RuntimeException;
use function sprintf;

/**
 * Every language server the report describes, in the order the table shows
 * them.
 *
 * The `intelephense` and `psalm` keys are the same entries the analyzer
 * catalog reads: one artifact, one release, two tables. See
 * [[LanguageServerMetadata]] for why both tables have a record for them.
 */
final class LanguageServerCatalog
{
    /**
     * Ordered by initial release, oldest first.
     *
     * @var array<string, class-string<LanguageServerMetadata>>
     */
    private const SERVERS = [
        'intelephense' => Intelephense::class,
        'phpactor' => Phpactor::class,
        'psalm' => Psalm::class,
        'devsense-php-ls' => DevsensePhpLs::class,
        'phpantom' => Phpantom::class,
        'php-lsp' => PhpLsp::class,
        'phpunit-language-server' => PhpUnit::class,
    ];

    /**
     * @param list<LanguageServerMetadata> $servers
     */
    public function __construct(private readonly array $servers)
    {
    }

    /**
     * @param array<string, Release> $releases keyed by artifact; see [[ReleaseTable]]
     */
    public static function build(array $releases): self
    {
        $servers = [];

        foreach (self::SERVERS as $tool => $class) {
            $release = $releases[$tool] ?? throw new RuntimeException(
                sprintf('No release recorded for language server: %s', $tool),
            );

            $servers[] = new $class($tool, $release);
        }

        return new self($servers);
    }

    /**
     * @return list<LanguageServerMetadata> in table order
     */
    public function all(): array
    {
        return $this->servers;
    }
}
