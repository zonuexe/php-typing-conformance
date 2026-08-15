<?php

declare(strict_types=1);

namespace Conformance\Metadata\LanguageServer;

use Conformance\Metadata\DiagnosticsSource;
use Conformance\Metadata\LanguageServerMetadata;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;
use Conformance\Metadata\ReleaseFeed;

/**
 * Crane, the Node-based PHP language server by Hvy Industries.
 *
 * Written in TypeScript on the php-parser npm package (glayzzle/php-parser);
 * at the time the project was active that parser supported PHP through 7.1.
 * Archived; the last release, v0.3.8, shipped in 2017.
 *
 * Historical: never launched by run-lsp-probes, listed for reference only.
 */
final class Crane extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'crane';
    }

    public function url(): string
    {
        return 'https://github.com/HvyIndustries/crane';
    }

    public function historical(): bool
    {
        return true;
    }

    public function diagnostics(): DiagnosticsSource
    {
        return DiagnosticsSource::OwnEngine;
    }

    public function analyzersDriven(): array
    {
        return [];
    }

    public function bundled(): string
    {
        return 'LSP server; PHP stubs installer for built-in class completion';
    }

    public function languages(): array
    {
        return ['TypeScript'];
    }

    public function founders(): array
    {
        return [new Person('Hvy Industries', 'https://github.com/HvyIndustries')];
    }

    public function organization(): Organization
    {
        return Organization::community('Hvy Industries', 'https://github.com/HvyIndustries');
    }

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
        return 2016;
    }

    public function parser(): ?string
    {
        return 'php-parser (npm)';
    }

    public function parserNote(): ?string
    {
        return 'The glayzzle/php-parser npm package; the parser\'s PHP support reached roughly 7.1 while Crane was active.';
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('HvyIndustries/crane');
    }
}
