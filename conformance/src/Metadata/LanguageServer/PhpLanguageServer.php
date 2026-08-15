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
 * php-language-server, the original community PHP language server.
 *
 * The first widely used PHP LSP server (2016, Felix Becker), superseded by
 * phpactor and intelephense. Written in PHP on top of
 * microsoft/tolerant-php-parser, whose recovery-oriented parsing set the
 * project's ceiling at roughly PHP 7.2 syntax; the last release, v5.4.6,
 * shipped in 2018.
 *
 * Historical: never launched by run-lsp-probes, listed for reference only.
 */
final class PhpLanguageServer extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'php-language-server';
    }

    public function url(): string
    {
        return 'https://github.com/felixfbecker/php-language-server';
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
        return 'LSP server only; the ancestor of today\'s PHP tooling, superseded by phpactor and intelephense';
    }

    public function languages(): array
    {
        return ['PHP'];
    }

    public function founders(): array
    {
        return [new Person('Felix Becker', 'https://github.com/felixfbecker')];
    }

    public function organization(): Organization
    {
        return Organization::personal();
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Felix Becker');
    }

    public function license(): string
    {
        return 'ISC';
    }

    public function initialReleaseYear(): int
    {
        return 2016;
    }

    public function parser(): ?string
    {
        return 'microsoft/tolerant-php-parser';
    }

    public function parserNote(): ?string
    {
        return 'Adopted the recovery-tolerant parser from Microsoft; the project\'s PHP syntax support was capped around PHP 7.2.';
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('felixfbecker/php-language-server');
    }
}
