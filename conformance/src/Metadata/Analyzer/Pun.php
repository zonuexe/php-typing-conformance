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

final class Pun extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'Pun';
    }

    public function homepage(): string
    {
        return 'https://pun.laravel.cloud';
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
        return ['Formatter', 'linter', 'package manager'];
    }

    public function languages(): array
    {
        return ['Rust'];
    }

    public function founders(): array
    {
        return [new Person('Pushpak Chhajed', 'https://github.com/pushpak1300')];
    }

    public function organization(): Organization
    {
        return Organization::company('Ship Fast Labs', 'https://github.com/shipfastlabs');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Pushpak Chhajed');
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
        return 'Mago parser (fork)';
    }

    protected function versionPattern(): ?string
    {
        return '/pun\s+(\d+\.\d+\.\d+)/i';
    }

    public function releaseUrl(string $version): ?string
    {
        return sprintf('https://github.com/shipfastlabs/pun/releases/tag/v%s', $version);
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('shipfastlabs/pun');
    }
}
