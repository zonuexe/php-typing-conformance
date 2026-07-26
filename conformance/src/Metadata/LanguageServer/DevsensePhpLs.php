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
 * DEVSENSE's standalone language server.
 *
 * The counterpart to php-lsp/mir: phpy, which the analyzer table does have a
 * record for, is the CLI frontend of *this* package rather than a separate
 * product — phpy's package.json depends on devsense-php-ls outright, and
 * DEVSENSE's own announcement calls phpy "a proof of concept for our new
 * standalone language server". The two are versioned independently and have
 * drifted, which is why each keeps its own record and its own release date
 * rather than sharing one entry the way Psalm and Intelephense do.
 */
final class DevsensePhpLs extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'devsense-php-ls';
    }

    public function url(): string
    {
        return 'https://www.npmjs.com/package/devsense-php-ls';
    }

    public function diagnostics(): DiagnosticsSource
    {
        return DiagnosticsSource::OwnEngine;
    }

    public function diagnosticsNote(): ?string
    {
        return 'Closed source, shipped as per-OS/CPU native binaries under node_modules. The same engine that phpy wraps and that PHP Tools for Visual Studio and VS Code embed.';
    }

    public function analyzersDriven(): array
    {
        return [];
    }

    public function analyzersDrivenNote(): ?string
    {
        return 'DEVSENSE claims “support for PHPStan, Psalm, PHPDoc Generics, Laravel Idea, and other annotations” — reading those annotation dialects, not running those tools. No DEVSENSE source describes invoking a third-party analyzer.';
    }

    public function bundled(): string
    {
        return 'Formatter, phpy CLI frontend';
    }

    public function languages(): array
    {
        return [];
    }

    public function languagesNote(): ?string
    {
        return 'DEVSENSE does not state the engine’s implementation language for this package. It ships as native per-OS binaries (devsense-php-ls-darwin-arm64 and siblings), and the company’s Phalanger heritage is C#/.NET, but neither is claimed for this artifact.';
    }

    public function founders(): array
    {
        return [new Person('Jakub Míšek', 'https://github.com/jakubmisek')];
    }

    public function organization(): Organization
    {
        return Organization::company('DEVSENSE', 'https://www.devsense.com');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Jakub Míšek');
    }

    public function license(): string
    {
        return 'Proprietary (freemium)';
    }

    public function licenseNote(): ?string
    {
        return 'package.json declares ISC, which covers the npm wrapper only: the README states the functionality itself is “provided to end-users under a freemium model”, activated with a license key. DEVSENSE’s Community License is free for OSI-licensed open source, education, and any entity under 250 seats and US$1M revenue.';
    }

    public function initialReleaseYear(): int
    {
        return 2025;
    }

    public function parser(): ?string
    {
        return null;
    }

    public function parserNote(): ?string
    {
        return 'No DEVSENSE page names the parser or AST behind the engine.';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://blog.devsense.com/2025/update-1-58-benchmarks/',
            'A standalone language server',
        );
    }
}
