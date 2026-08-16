<?php

declare(strict_types=1);

namespace Conformance\Metadata\LanguageServer;

use Conformance\Metadata\Announcement;
use Conformance\Metadata\DiagnosticsSource;
use Conformance\Metadata\LanguageServerMetadata;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;
use Conformance\Metadata\ReleaseFeed;

/**
 * Laravel LSP — first-party framework-aware server for Laravel and Blade.
 *
 * It is function-specific: hover, completion, definition, document links and
 * diagnostics are about routes, views, config, env, middleware and the rest
 * of the Laravel surface, not about PHP types. The suite still launches it,
 * against a Laravel-shaped workspace, because that is the only way to ask
 * whether those framework features answer. The general PHP hover and
 * navigation layers stay empty, and that emptiness is the measurement.
 *
 * The initialize handshake refuses a workspace that has no `artisan` file.
 */
final class LaravelLsp extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'Laravel LSP';
    }

    public function url(): string
    {
        return 'https://github.com/laravel/lsp';
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
        return 'Own diagnostics for Laravel symbols (unknown env keys, missing views, unknown routes, …), published over push diagnostics. The README does not claim PHP type checking, and the handshake advertises no diagnosticProvider.';
    }

    public function analyzersDriven(): array
    {
        return [];
    }

    public function bundled(): string
    {
        return 'Blade, Pest helper generation, document links, code actions';
    }

    public function bundledNote(): ?string
    {
        return 'The README lists completions, hovers, diagnostics, document links, go-to-definition and code actions for Laravel and Blade. Pest helper docblocks are generated into storage/framework/testing/_pest.php unless disabled. Definition is resolved from document links.';
    }

    public function languages(): array
    {
        return ['PHP'];
    }

    public function languagesNote(): ?string
    {
        return 'A Laravel Zero CLI compiled into the laravel-lsp binary. composer.json and AGENTS.md describe it as a PHP application.';
    }

    public function founders(): array
    {
        return [new Person('Taylor Otwell', 'https://github.com/taylorotwell')];
    }

    public function founderEmployer(): ?string
    {
        return 'Laravel';
    }

    public function organization(): Organization
    {
        return Organization::company('Laravel', 'https://laravel.com');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Taylor Otwell');
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
        return 'First Packagist tag 0.0.1 on 2026-05-14.';
    }

    public function parser(): ?string
    {
        return 'microsoft/tolerant-php-parser, stillat/blade-parser';
    }

    public function parserNote(): ?string
    {
        return 'composer.json require-dev pins both; AGENTS.md says the server “parses PHP and Blade code”. They are build-time dependencies of the compiled binary, not something a user installs.';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://laravel.com/docs/13.x/installation',
            'Laravel 13 installation docs',
        );
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::packagist('laravel/lsp');
    }
}
