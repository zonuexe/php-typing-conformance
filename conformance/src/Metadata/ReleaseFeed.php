<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use function sprintf;

/**
 * The machine-readable side of "Latest release": where to ask.
 *
 * The report's release table is curated data, but this one column is a fact
 * anyone can look up, so each project says where its releases are announced
 * and update-tools.php goes and reads it.
 */
final readonly class ReleaseFeed
{
    private function __construct(
        public ReleaseFeedKind $kind,
        public string $id,
    ) {
    }

    /** @param string $repo owner/name, e.g. `phan/phan` */
    public static function gitHub(string $repo): self
    {
        return new self(ReleaseFeedKind::GitHub, $repo);
    }

    public static function npm(string $package): self
    {
        return new self(ReleaseFeedKind::Npm, $package);
    }

    /** @param string $package vendor/name, e.g. `typedduck/steins` */
    public static function packagist(string $package): self
    {
        return new self(ReleaseFeedKind::Packagist, $package);
    }

    public function url(): string
    {
        return match ($this->kind) {
            ReleaseFeedKind::GitHub => sprintf('https://api.github.com/repos/%s/releases/latest', $this->id),
            ReleaseFeedKind::Npm => sprintf('https://registry.npmjs.org/%s', $this->id),
            ReleaseFeedKind::Packagist => sprintf('https://repo.packagist.org/p2/%s.json', $this->id),
        };
    }

    /** How the feed is named in output, e.g. `github:phan/phan`. */
    public function label(): string
    {
        return sprintf('%s:%s', strtolower($this->kind->name), $this->id);
    }
}
