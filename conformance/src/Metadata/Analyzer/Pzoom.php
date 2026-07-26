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

/**
 * Pzoom, the Rust port of Psalm by Psalm's own founder.
 *
 * Its results are folded into the Psalm column of the matrix rather than given
 * one of their own; this row is where the port is stated as its own artifact.
 */
final class Pzoom extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'Pzoom';
    }

    public function homepage(): string
    {
        return 'https://pzoom.dev';
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
        return [new Person('Matt Brown', 'https://github.com/muglug')];
    }

    public function organization(): Organization
    {
        return Organization::personal();
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Matt Brown');
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
        return 'Mago parser';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://mattbrown.dev/articles/from-psalm-to-pzoom',
            'From Psalm to Pzoom',
        );
    }
}
