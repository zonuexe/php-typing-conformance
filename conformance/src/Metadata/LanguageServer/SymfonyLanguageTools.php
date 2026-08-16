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
 * Symfony Language Tools — first-party Symfony-aware server.
 *
 * Function-specific: completion, hover, navigation, references, diagnostics,
 * code actions, rename and code lenses are about Symfony surfaces (routing,
 * DI, Twig, translations, env, config, Messenger, events, Security, forms,
 * validation, serializer, AssetMapper, Stimulus, Live Components, Doctrine),
 * not about PHP types. The README says it works alongside a general PHP
 * language server. The suite does not launch it: the report lists it in the
 * function-specific table for reference only.
 *
 * The product was renamed from Symfony LSP in 0.8.6; the binary and the
 * catalog key stay `symfony-lsp`.
 */
final class SymfonyLanguageTools extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'Symfony Language Tools';
    }

    public function url(): string
    {
        return 'https://github.com/symfony/language-tools';
    }

    public function specialized(): bool
    {
        return true;
    }

    public function diagnostics(): DiagnosticsSource
    {
        return DiagnosticsSource::OwnEngine;
    }

    public function diagnosticsNote(): ?string
    {
        return 'Own diagnostics for Symfony symbols (routes, services, templates, translations, env, configuration, …). The README does not claim PHP type checking; it says the server works alongside a general PHP language server.';
    }

    public function analyzersDriven(): array
    {
        return [];
    }

    public function bundled(): string
    {
        return 'Twig, Doctrine, Messenger, Security, forms, validation, serializer, AssetMapper, Stimulus, Live Components; code actions, rename, code lenses';
    }

    public function bundledNote(): ?string
    {
        return 'The README lists Symfony-aware completion, hover, navigation, references, diagnostics, code actions, rename and code lenses. Features cover routing, dependency injection, Twig, translations, environment variables, bundle configuration, Messenger, events, Security, forms, validation, serializer metadata, AssetMapper, Stimulus, Live Components and Doctrine.';
    }

    public function languages(): array
    {
        return ['PHP'];
    }

    public function languagesNote(): ?string
    {
        return 'composer.json is the PHP project `symfony/lsp` (namespace Symfony\\Lsp). Standalone releases ship a compiled `symfony-lsp` binary plus a `symfony-lsp-tree-sitter` sidecar that must stay in the same directory.';
    }

    public function founders(): array
    {
        return [new Person('Fabien Potencier', 'https://github.com/fabpot')];
    }

    public function founderEmployer(): ?string
    {
        return 'Symfony';
    }

    public function organization(): Organization
    {
        return Organization::company('Symfony', 'https://symfony.com');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Fabien Potencier');
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2026;
    }

    public function initialReleaseNote(): ?string
    {
        return 'First GitHub release v0.1.2 on 2026-08-01.';
    }

    public function parser(): ?string
    {
        return 'fabpot/tolerant-php-parser, tree-sitter';
    }

    public function parserNote(): ?string
    {
        return 'composer.json requires fabpot/tolerant-php-parser. The 0.1.2 changelog calls out tolerant PHP, Twig and YAML source parsing. The standalone install keeps the tree-sitter sidecar next to the server.';
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('symfony/language-tools');
    }
}
