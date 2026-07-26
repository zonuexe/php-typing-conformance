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

final class Phan extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'Phan';
    }

    public function homepage(): string
    {
        return 'https://github.com/phan/phan';
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
        return ['Fixer (narrow)'];
    }

    public function languages(): array
    {
        return ['PHP'];
    }

    public function founders(): array
    {
        return [
            new Person('Rasmus Lerdorf', 'https://github.com/rlerdorf'),
            new Person('Andrew Morrison', 'https://github.com/morria'),
        ];
    }

    public function founderEmployer(): ?string
    {
        return 'Etsy';
    }

    public function organization(): Organization
    {
        return Organization::community('phan', 'https://github.com/phan');
    }

    /**
     * Rasmus Lerdorf, not Tyson Andre, who is widely cited elsewhere as the
     * current maintainer. Linked despite also being a founder, because the
     * evidence for the correction belongs in the note.
     */
    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer(
            'Rasmus Lerdorf',
            'https://github.com/rlerdorf',
            'Every one of the last 10 GitHub releases (5.5.2 through 6.0.7) was cut by rlerdorf; Tyson Andre has no recent commits or releases despite being widely cited elsewhere as the current maintainer.',
        );
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2015;
    }

    public function parser(): string
    {
        return 'ext-ast / tolerant-php-parser';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement('https://talks.php.net/ph16', 'Deploying PHP 7 (talk)');
    }
}
