<?php

declare(strict_types=1);

namespace Conformance\Metadata\Analyzer;

use Conformance\Metadata\AnalysisKind;
use Conformance\Metadata\AnalyzerMetadata;
use Conformance\Metadata\Announcement;
use Conformance\Metadata\InterfaceKind;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;
use Conformance\Metadata\ReleaseFeed;
use function sprintf;

final class Psalm extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'Psalm';
    }

    /**
     * Phrased as "also referred to as" rather than as an expansion: its
     * creator's own framing ("or, if you prefer, the PHP Static Analysis
     * Linting Machine", Vimeo Engineering Blog, 2018) makes it an optional
     * backronym, not the name's origin — unlike PHPStan, whose README and
     * composer.json read "PHPStan - PHP Static Analysis Tool" outright.
     */
    public function expansion(): ?string
    {
        return 'Also referred to as a “PHP Static Analysis Linting Machine”';
    }

    public function homepage(): string
    {
        return 'https://psalm.dev';
    }

    public function analysis(): AnalysisKind
    {
        return AnalysisKind::TypeChecker;
    }

    public function interfaces(): array
    {
        return [InterfaceKind::Cli, InterfaceKind::Lsp];
    }

    public function bundled(): array
    {
        return ['Fixer', 'refactorer'];
    }

    public function languages(): array
    {
        return ['PHP'];
    }

    public function founders(): array
    {
        return [new Person('Matt Brown', 'https://github.com/muglug')];
    }

    public function founderEmployer(): ?string
    {
        return 'Vimeo';
    }

    /**
     * The github.com/psalm community-packages org, not vimeo/psalm: the
     * repository still lives under Vimeo's org, but no company is behind the
     * project any more.
     */
    public function organization(): Organization
    {
        return Organization::community('psalm', 'https://github.com/psalm');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer(
            'Daniil Gentili',
            'https://github.com/danog',
            'Sole active maintainer since Vimeo stepped back; the repository still lives under the vimeo/psalm GitHub org.',
        );
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2016;
    }

    public function parser(): string
    {
        return 'nikic/PHP-Parser';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://medium.com/vimeo-engineering-blog/automated-type-inference-for-dynamically-typed-programs-6e79197e5420',
            'Automated type inference',
        );
    }

    protected function versionPattern(): ?string
    {
        // The suffix matters for the 7.x line: the version cell must show
        // 7.0.0-beta19, not 7.0.0, so the release link points at the tag
        // that actually exists.
        return '/Psalm\s+(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)/';
    }

    /**
     * Cut under vimeo/psalm, not under the github.com/psalm org the
     * Organization column links to.
     */
    public function releaseUrl(string $version): ?string
    {
        return sprintf('https://github.com/vimeo/psalm/releases/tag/%s', $version);
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('vimeo/psalm');
    }
}
