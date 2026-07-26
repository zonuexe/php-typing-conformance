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
 * PHPantom.
 *
 * Note that Mago plays two roles in this record: an optional external analyzer
 * it can drive, and the parser the server itself is built on.
 */
final class Phpantom extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'PHPantom';
    }

    public function url(): string
    {
        return 'https://phpantom-dev.github.io/phpantom_lsp/';
    }

    public function diagnostics(): DiagnosticsSource
    {
        return DiagnosticsSource::OwnEngineAndAdapter;
    }

    public function diagnosticsNote(): ?string
    {
        return 'Its own type engine produces the diagnostics; the docs additionally advertise “PHPStan, PHPCS, and Mago integration. Run external tools on save and surface their diagnostics in the editor.”';
    }

    public function analyzersDriven(): array
    {
        return ['PHPStan', 'PHPCS', 'Mago'];
    }

    public function analyzersDrivenNote(): ?string
    {
        return 'Bundled in the single binary rather than plugins, invoked as subprocesses and auto-detected from vendor/bin then $PATH; Mago only when a mago.toml exists at the workspace root. Note Mago plays two roles here — optional external analyzer, and the parser the server itself is built on.';
    }

    public function bundled(): string
    {
        return 'Formatter, refactorings, CLI (analyze, fix)';
    }

    public function languages(): array
    {
        return ['Rust'];
    }

    public function founders(): array
    {
        return [new Person('Anders Jenbo', 'https://github.com/AJenbo')];
    }

    public function organization(): Organization
    {
        return Organization::community('PHPantom-dev', 'https://github.com/PHPantom-dev');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Anders Jenbo');
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
        return 'Mago parser (mago-syntax)';
    }

    public function parserNote(): ?string
    {
        return 'The README credits “Mago: the PHP parser that powers all of PHPantom’s AST analysis”; Cargo.toml exact-pins mago-syntax and a half-dozen sibling mago-* crates.';
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('PHPantom-dev/phpantom_lsp');
    }
}
