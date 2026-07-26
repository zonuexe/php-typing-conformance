<?php

declare(strict_types=1);

namespace Conformance\Metadata\LanguageServer;

use Conformance\Metadata\Announcement;
use Conformance\Metadata\DiagnosticsSource;
use Conformance\Metadata\LanguageServerMetadata;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;

/**
 * Psalm's language server: the same artifact the analyzer table has a record
 * for, reached over the protocol instead of the command line, and reading its
 * release from the same entry in the release table. The server builds the same
 * ProjectAnalyzer the CLI does, which is why one engine can honestly appear in
 * both tables.
 */
final class Psalm extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'Psalm';
    }

    public function url(): string
    {
        return 'https://psalm.dev/docs/running_psalm/language_server/';
    }

    public function diagnostics(): DiagnosticsSource
    {
        return DiagnosticsSource::OwnEngine;
    }

    public function diagnosticsNote(): ?string
    {
        return 'Literally the same engine as the CLI: the server builds a ProjectAnalyzer and Codebase, calls analyzeFiles(), and maps Psalm’s own IssueData onto LSP Diagnostic objects. The v3 announcement describes what the editor shows as Psalm’s “regular error reports”.';
    }

    public function analyzersDriven(): array
    {
        return [];
    }

    public function analyzersDrivenNote(): ?string
    {
        return 'None claimed. Neither the docs nor the server source mentions running PHPStan, PHP_CodeSniffer or php-cs-fixer.';
    }

    public function bundled(): string
    {
        return 'Psalm CLI, Psalter fixer — one package';
    }

    public function bundledNote(): ?string
    {
        return 'The server is the psalm-language-server bin inside vimeo/psalm, never a separate package. Its own docs scope it tightly: “diagnostics …, go-to-definition and hover, with limited support for autocompletion (PRs are welcome!)”, and the server declares documentSymbolProvider, workspaceSymbolProvider, referencesProvider and documentHighlightProvider as false outright, with no rename or formatting provider at all. Completion is opt-in; code actions require the client to advertise publishDiagnostics.dataSupport.';
    }

    public function languages(): array
    {
        return ['PHP'];
    }

    public function founders(): array
    {
        return [new Person('Matt Brown', 'https://github.com/muglug')];
    }

    public function founderEmployer(): ?string
    {
        return 'Vimeo';
    }

    public function organization(): Organization
    {
        return Organization::community('psalm', 'https://github.com/psalm');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Daniil Gentili', 'https://github.com/danog', 'The README names him “the only active maintainer of Psalm”, and he is also the most active author on the LanguageServer directory itself. The VS Code client is the exception: it lives in its own repo, psalm/psalm-vscode-plugin, where Andrew Nagy does most of the committing and merging.');
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2018;
    }

    public function initialReleaseNote(): ?string
    {
        return 'The server binary landed quietly in 2.0.15 (2018-10-19), a commit titled “Add server mode support with error reporting only”; the psalm-language-server bin was in composer.json by that tag. It only got a public announcement two months later, with Psalm 3.';
    }

    public function parser(): ?string
    {
        return 'nikic/PHP-Parser';
    }

    public function parserNote(): ?string
    {
        return 'Same parser as the CLI. The LSP wire types are not in-tree either — they come from felixfbecker/language-server-protocol and danog/advanced-json-rpc.';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://psalm.dev/articles/announcing-psalm-v3',
            'Announcing Psalm v3',
        );
    }
}
