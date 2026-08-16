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

/**
 * Phpactor — the same artifact the language-server table carries, reached here
 * through its `worse:analyse` CLI command, the way Intelephense and PHPantom
 * are: one binary, one release, two interfaces.
 *
 * Filed as code intelligence rather than as a type checker on the author's own
 * account: Phpactor's diagnostics exist mainly to feed its code actions, which
 * is a different aim from the checkers that surround it in the matrix.
 */
final class Phpactor extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'Phpactor';
    }

    public function homepage(): string
    {
        return 'https://phpactor.readthedocs.io/en/master/';
    }

    public function analysis(): AnalysisKind
    {
        return AnalysisKind::CodeIntelligence;
    }

    public function interfaces(): array
    {
        return [InterfaceKind::Cli, InterfaceKind::Lsp];
    }

    public function bundled(): array
    {
        return ['Refactorings', 'code generation', 'VIM plugin'];
    }

    public function languages(): array
    {
        return ['PHP'];
    }

    public function founders(): array
    {
        return [new Person('Dan Leech', 'https://github.com/dantleech')];
    }

    public function organization(): Organization
    {
        return Organization::community('phpactor', 'https://github.com/phpactor');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Dan Leech');
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2018;
    }

    public function parser(): string
    {
        return 'tolerant-php-parser (fork)';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://www.dantleech.com/blog/2018/08/19/three-years-of-phpactor/',
            'Three Years of Phpactor',
        );
    }

    protected function versionPattern(): ?string
    {
        return '/Phpactor\s+(\d{4}\.\d{2}\.\d{2}\.\d+)/i';
    }

    public function releaseUrl(string $version): ?string
    {
        return sprintf('https://github.com/phpactor/phpactor/releases/tag/%s', $version);
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('phpactor/phpactor');
    }
}
