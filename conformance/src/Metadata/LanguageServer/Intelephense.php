<?php

declare(strict_types=1);

namespace Conformance\Metadata\LanguageServer;

use Conformance\Metadata\DiagnosticsSource;
use Conformance\Metadata\LanguageServerMetadata;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;

/**
 * Intelephense's language server — the same artifact the analyzer table has a
 * record for, which is why both read their release from one entry in the
 * release table. That table records how it scores; this one records what it
 * is.
 */
final class Intelephense extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'Intelephense';
    }

    public function url(): string
    {
        return 'https://intelephense.com';
    }

    public function diagnostics(): DiagnosticsSource
    {
        return DiagnosticsSource::OwnEngine;
    }

    public function diagnosticsNote(): ?string
    {
        return 'Closed source. Its own wording is “multiple diagnostics for open files via an error tolerant parser and powerful static analysis engine”. Its settings contract reaches past undefined-symbol checks into real type diagnostics: intelephense.diagnostics.typeErrors covers “type compatibility for assignments and returns”, alongside argumentCount, implementationErrors, memberAccess and strictTypes.';
    }

    public function analyzersDriven(): array
    {
        return [];
    }

    public function analyzersDrivenNote(): ?string
    {
        return 'None claimed: no Intelephense source mentions running PHPStan, Psalm, Phan or PHP_CodeSniffer. It instead claims to read their PHPDoc dialects itself — “advanced PHPDoc type system supporting templates and callable signatures”, @psalm-assert, class-string<T>, conditional and DNF types — plus PHPStorm metadata files.';
    }

    public function bundled(): string
    {
        return 'Formatter, rename, code actions, inlay hints';
    }

    public function languages(): array
    {
        return ['TypeScript'];
    }

    public function founders(): array
    {
        return [new Person('Ben Mewburn', 'https://github.com/bmewburn')];
    }

    public function organization(): Organization
    {
        return Organization::company('Intelephense', 'https://intelephense.com');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Ben Mewburn');
    }

    public function license(): string
    {
        return 'Proprietary (freemium)';
    }

    public function licenseNote(): ?string
    {
        return 'Its own words: “Intelephense is released to end users under a freemium model.” Free covers completion, signature help, go-to-definition, find references, symbol search, hover, PSR-12 formatting and the diagnostics themselves. A one-off key (US$35 personal, US$75/user business) unlocks rename, code actions, code lens, inlay hints, type hierarchy, find implementations, go-to-type-definition, code folding and @mixin support. So the diagnostics this table is about are entirely in the free tier.';
    }

    public function initialReleaseYear(): int
    {
        return 2017;
    }

    public function parser(): ?string
    {
        return 'own parser';
    }

    public function parserNote(): ?string
    {
        return 'Never described as “own parser” in so many words — the developer’s own phrase is “error tolerant parser”. Read as in-house because early versions depended on php7parser, which Ben Mewburn also wrote; the current server bundles its parser closed-source, so the dependency is no longer visible.';
    }
}
