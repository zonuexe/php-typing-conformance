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
 * php-lsp, the server built on mir.
 *
 * "Own engine" even though the engine is a separate repository and crate
 * family: php-lsp's own architecture doc frames mir as its static-analysis
 * engine rather than as a third-party tool it adapts, the two share an author,
 * and the CHANGELOG records mir being extracted from a sibling path dependency
 * rather than adopted from outside. mir has its own record in the analyzer
 * table, which analyzersAreInHouse() marks so the relationship stays visible.
 */
final class PhpLsp extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'php-lsp';
    }

    public function url(): string
    {
        return 'https://github.com/jorgsowa/php-lsp';
    }

    public function diagnostics(): DiagnosticsSource
    {
        return DiagnosticsSource::OwnEngine;
    }

    public function diagnosticsNote(): ?string
    {
        return 'Its architecture doc names the mir-php crates — mir-analyzer, mir-codebase, mir-issues, mir-types — as its static analysis, and the CHANGELOG records the older in-tree TypeMap inference being deleted once “mir is now the sole source of truth for all type inference”.';
    }

    public function analyzersDriven(): array
    {
        return ['mir'];
    }

    public function analyzersAreInHouse(): bool
    {
        return true;
    }

    public function analyzersDrivenNote(): ?string
    {
        return 'mir has its own row in the analyzer table above. It is a separate repository and crate family, but by the same author, and the CHANGELOG shows it was extracted from a sibling path dependency rather than adopted from outside — so it is an in-house engine, not a third-party analyzer being adapted.';
    }

    public function bundled(): string
    {
        return 'Refactorings, code actions; formatting via php-cs-fixer or phpcbf';
    }

    public function languages(): array
    {
        return ['Rust'];
    }

    public function founders(): array
    {
        return [new Person('Jorg Sowa', 'https://github.com/jorgsowa')];
    }

    public function organization(): Organization
    {
        return Organization::personal();
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Jorg Sowa');
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2026;
    }

    public function parser(): ?string
    {
        return 'own (php-rs-parser)';
    }

    public function parserNote(): ?string
    {
        return 'Dedicated php-rs-parser and php-ast crates, published separately by the same author.';
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('jorgsowa/php-lsp');
    }
}
