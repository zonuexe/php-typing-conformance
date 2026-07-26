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
use function sprintf;

final class Mago extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'Mago';
    }

    public function homepage(): string
    {
        return 'https://mago.carthage.software';
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
        return ['Linter', 'formatter', 'arch guard'];
    }

    public function languages(): array
    {
        return ['Rust'];
    }

    public function founders(): array
    {
        return [new Person('Saif Eddin Gmati', 'https://github.com/azjezz')];
    }

    public function organization(): Organization
    {
        return Organization::company('Carthage Software', 'https://github.com/carthage-software');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Saif Eddin Gmati');
    }

    public function license(): string
    {
        return 'MIT OR Apache-2.0';
    }

    public function initialReleaseYear(): int
    {
        return 2024;
    }

    public function parser(): string
    {
        return 'own parser';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://github.com/carthage-software/mago/releases/tag/1.0.0',
            'Mago 1.0.0',
        );
    }

    protected function versionPattern(): ?string
    {
        return '/mago\s+(\d+\.\d+\.\d+)/i';
    }

    public function releaseUrl(string $version): ?string
    {
        return sprintf('https://github.com/carthage-software/mago/releases/tag/%s', $version);
    }
}
