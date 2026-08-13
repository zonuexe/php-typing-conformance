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

/**
 * PHPantom — the same artifact the language-server table carries, reached
 * here through its `analyze` CLI. One binary, one release, two interfaces,
 * exactly like Intelephense and Psalm: the release entry lives in the
 * analyzers section and both tables read it.
 */
final class Phpantom extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'PHPantom';
    }

    public function homepage(): string
    {
        return 'https://phpantom-dev.github.io/phpantom_lsp/';
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
        return ['Formatter', 'Refactorings', 'fix CLI'];
    }

    public function languages(): array
    {
        return ['Rust'];
    }

    public function founders(): array
    {
        return [new Person('Anders Jenbo', 'https://github.com/AJenbo')];
    }

    public function organization(): Organization
    {
        return Organization::community('PHPantom-dev', 'https://github.com/PHPantom-dev');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Anders Jenbo');
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
        return 'Mago parser (mago-syntax)';
    }

    protected function versionPattern(): ?string
    {
        return '/phpantom_lsp\s+(\d+\.\d+\.\d+)/i';
    }

    public function releaseUrl(string $version): ?string
    {
        return sprintf('https://github.com/PHPantom-dev/phpantom_lsp/releases/tag/%s', $version);
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('PHPantom-dev/phpantom_lsp');
    }
}
