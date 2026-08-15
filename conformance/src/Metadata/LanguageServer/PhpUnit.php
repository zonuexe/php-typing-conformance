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
 * phpunit-language-server — the PHPUnit test-case server.
 *
 * A function-specific server: it serves PHPUnit test files, not PHP as a
 * language — code lens on test cases, document symbols, diagnostics, and
 * completion for the testcase/test/setup/teardown keywords. It has no hover,
 * no type inference and no navigation on ordinary code, so the suite does
 * not launch it: the report lists it in the function-specific table for
 * reference only.
 *
 * The artifact described here is the npm server package; `vscode-phpunit`
 * is the same project's VS Code extension (the client), and the two are
 * versioned separately.
 */
final class PhpUnit extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'phpunit-language-server';
    }

    public function url(): string
    {
        return 'https://www.npmjs.com/package/phpunit-language-server';
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
        return 'Publishes diagnostics for PHPUnit test files from its own parsing; as-you-type reporting of parsing and compilation errors is listed as planned.';
    }

    public function analyzersDriven(): array
    {
        return [];
    }

    public function bundled(): string
    {
        return 'PHPUnit test-case features: code lens, document symbols, test-keyword completion';
    }

    public function languages(): array
    {
        return [];
    }

    public function languagesNote(): ?string
    {
        return 'The npm package ships bin/server.js, a Node entry point, but neither the README nor the package metadata claims the implementation language for this artifact.';
    }

    public function founders(): array
    {
        return [new Person('recca0120', 'https://github.com/recca0120')];
    }

    public function organization(): Organization
    {
        return Organization::personal();
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('recca0120');
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2018;
    }

    public function parser(): ?string
    {
        return null;
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::npm('phpunit-language-server');
    }
}
