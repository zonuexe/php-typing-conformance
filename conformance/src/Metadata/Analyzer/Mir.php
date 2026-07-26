<?php

declare(strict_types=1);

namespace Conformance\Metadata\Analyzer;

use Conformance\Metadata\AnalysisKind;
use Conformance\Metadata\AnalyzerMetadata;
use Conformance\Metadata\InterfaceKind;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;
use Conformance\Metadata\ReleaseFeed;
use function sprintf;

final class Mir extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'mir';
    }

    public function homepage(): string
    {
        return 'https://github.com/jorgsowa/mir';
    }

    public function analysis(): AnalysisKind
    {
        return AnalysisKind::TypeChecker;
    }

    public function interfaces(): array
    {
        return [InterfaceKind::Cli];
    }

    public function bundled(): array
    {
        return [];
    }

    public function languages(): array
    {
        return ['Rust'];
    }

    public function founders(): array
    {
        return [new Person('Jorg Sowa', 'https://github.com/jorgsowa')];
    }

    /** jorgsowa is a user account, not an org: genuinely one person's project. */
    public function organization(): Organization
    {
        return Organization::personal();
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Jorg Sowa');
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2026;
    }

    public function parser(): string
    {
        return 'own (php-rs-parser)';
    }

    protected function versionPattern(): ?string
    {
        return '/mir\s+(\d+\.\d+\.\d+)/i';
    }

    public function releaseUrl(string $version): ?string
    {
        return sprintf('https://github.com/jorgsowa/mir/releases/tag/v%s', $version);
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('jorgsowa/mir');
    }
}
