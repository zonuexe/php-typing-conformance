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

final class NoVerify extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'NoVerify';
    }

    public function homepage(): string
    {
        return 'https://github.com/VKCOM/noverify';
    }

    public function analysis(): AnalysisKind
    {
        return AnalysisKind::TypeAwareLinter;
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
        return ['Go'];
    }

    public function founders(): array
    {
        return [new Person('Yuriy Nasretdinov', 'https://github.com/YuriyNasretdinov')];
    }

    public function founderEmployer(): ?string
    {
        return 'VK';
    }

    /** Named as the org's own page names itself — "VK.COM", not the VKCOM slug. */
    public function organization(): Organization
    {
        return Organization::company('VK.COM', 'https://github.com/VKCOM');
    }

    /**
     * Left unstated rather than guessed. The recent releases (v0.5.4, v0.5.5)
     * were cut by someone other than the founder, but that alone does not
     * establish who currently drives the project — unlike Phan's unambiguous
     * single-author release history.
     */
    public function lead(): ?LeadMaintainer
    {
        return null;
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2019;
    }

    public function parser(): string
    {
        return 'VKCOM/php-parser';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://habr.com/ru/companies/vk/articles/442284/',
            'VK open-sources it (Habr)',
        );
    }
}
