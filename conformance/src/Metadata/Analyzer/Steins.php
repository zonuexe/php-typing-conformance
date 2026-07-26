<?php

declare(strict_types=1);

namespace Conformance\Metadata\Analyzer;

use Conformance\Metadata\AnalysisKind;
use Conformance\Metadata\AnalyzerMetadata;
use Conformance\Metadata\InterfaceKind;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;

final class Steins extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'PHP;STEINS';
    }

    public function homepage(): string
    {
        return 'https://github.com/rigortype/steins';
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
        return ['Annotator', 'transforms'];
    }

    public function languages(): array
    {
        return ['Rust'];
    }

    public function founders(): array
    {
        return [new Person('USAMI Kenta', 'https://github.com/zonuexe')];
    }

    /** Named as the org's own page names itself — "TypedDuck", not rigortype. */
    public function organization(): Organization
    {
        return Organization::company('TypedDuck', 'https://github.com/rigortype');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('USAMI Kenta');
    }

    public function license(): string
    {
        return 'Apache-2.0';
    }

    public function initialReleaseYear(): int
    {
        return 2026;
    }

    public function parser(): string
    {
        return 'Mago parser (fork)';
    }
}
