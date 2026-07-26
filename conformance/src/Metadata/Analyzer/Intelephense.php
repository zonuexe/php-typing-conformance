<?php

declare(strict_types=1);

namespace Conformance\Metadata\Analyzer;

use Conformance\Metadata\AnalysisKind;
use Conformance\Metadata\AnalyzerMetadata;
use Conformance\Metadata\InterfaceKind;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;
use function sprintf;

final class Intelephense extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'Intelephense';
    }

    public function homepage(): string
    {
        return 'https://intelephense.com';
    }

    public function analysis(): AnalysisKind
    {
        return AnalysisKind::CodeIntelligence;
    }

    public function interfaces(): array
    {
        return [InterfaceKind::Lsp];
    }

    public function bundled(): array
    {
        return ['Formatter', 'rename'];
    }

    public function languages(): array
    {
        return ['TypeScript'];
    }

    public function founders(): array
    {
        return [new Person('Ben Mewburn', 'https://github.com/bmewburn')];
    }

    /**
     * A company, not just a product name: the site's footer carries an
     * Australian Business Number.
     */
    public function organization(): Organization
    {
        return Organization::company('Intelephense', 'https://intelephense.com');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Ben Mewburn');
    }

    public function license(): string
    {
        return 'Proprietary (freemium)';
    }

    public function initialReleaseYear(): int
    {
        return 2017;
    }

    public function parser(): string
    {
        return 'own parser';
    }

    protected function versionPattern(): ?string
    {
        return '/intelephense\s+(\d+\.\d+\.\d+)/i';
    }

    /**
     * Closed source, so npm is where a release is published at all.
     */
    public function releaseUrl(string $version): ?string
    {
        return sprintf('https://www.npmjs.com/package/intelephense/v/%s', $version);
    }
}
