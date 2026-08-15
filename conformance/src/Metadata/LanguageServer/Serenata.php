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
 * Serenata, the PHP-based language server on GitLab.
 *
 * Static analysis over the language server protocol — autocompletion,
 * linting, code navigation, tooltips — written in PHP on nikic/php-parser;
 * the parser line it tracked supports PHP 8.1. The project's GitLab group
 * owns it, with no individual founder named in the repository. Last release
 * 5.4.0 shipped 2020.
 *
 * Historical: never launched by run-lsp-probes, listed for reference only.
 */
final class Serenata extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'Serenata';
    }

    public function url(): string
    {
        return 'https://gitlab.com/Serenata/Serenata';
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
        return 'LSP server; autocompletion, linting, code navigation and tooltips';
    }

    public function languages(): array
    {
        return ['PHP'];
    }

    public function founders(): array
    {
        return [new Person('Serenata', 'https://gitlab.com/Serenata')];
    }

    public function organization(): Organization
    {
        return Organization::community('Serenata', 'https://gitlab.com/Serenata');
    }

    public function lead(): ?LeadMaintainer
    {
        return null;
    }

    public function license(): string
    {
        return 'AGPL-3.0';
    }

    public function initialReleaseYear(): int
    {
        return 2017;
    }

    public function parser(): ?string
    {
        return 'nikic/php-parser';
    }

    public function parserNote(): ?string
    {
        return 'The parser line it tracked supports PHP 8.1.';
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        // GitLab has no feed kind; the release table records the last release
        // as a curated fact and update-tools reports "no feed" for it.
        return null;
    }
}
