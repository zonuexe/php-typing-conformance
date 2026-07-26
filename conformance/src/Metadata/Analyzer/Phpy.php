<?php

declare(strict_types=1);

namespace Conformance\Metadata\Analyzer;

use Conformance\Metadata\AnalysisKind;
use Conformance\Metadata\AnalyzerMetadata;
use Conformance\Metadata\Announcement;
use Conformance\Metadata\InterfaceKind;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;
use Conformance\Metadata\ReleaseFeed;

/**
 * phpy, DEVSENSE's CLI wrapper around the PHP Tools analysis engine.
 *
 * The one row whose name states two things at once, in name() itself and not
 * as an <abbr> tooltip, because unlike every other row the thing being
 * compared is not the whole product: phpy (github.com/DEVSENSE/phpy) is a thin
 * CLI wrapper DEVSENSE published in 2025-05 (v0.1.0) around the same
 * closed-source engine that has shipped inside PHP Tools for Visual Studio
 * since 2012. DEVSENSE's own blog post introduces it as "a proof of concept"
 * for a standalone language server, not as a ground-up product.
 *
 * The initial release is therefore phpy's own 2025 start, not PHP Tools' 2012
 * origin: every row tracks the specific artifact being compared, not whatever
 * earlier tooling its maintainer previously built — NoVerify's row does not
 * inherit VK's earlier internal tooling either.
 */
final class Phpy extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'phpy / PHPTools';
    }

    public function homepage(): string
    {
        return 'https://github.com/DEVSENSE/phpy';
    }

    public function analysis(): AnalysisKind
    {
        return AnalysisKind::CodeIntelligence;
    }

    public function interfaces(): array
    {
        return [InterfaceKind::Cli, InterfaceKind::Lsp];
    }

    public function bundled(): array
    {
        return ['Formatter'];
    }

    public function languages(): array
    {
        return ['C#', 'TypeScript'];
    }

    public function founders(): array
    {
        return [new Person('Jakub Míšek', 'https://github.com/jakubmisek')];
    }

    public function organization(): Organization
    {
        return Organization::company('DEVSENSE', 'https://www.devsense.com');
    }

    /** DEVSENSE's founder, and the sole author on phpy's GitHub releases. */
    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Jakub Míšek');
    }

    /**
     * Verified against DEVSENSE's own Community License page: free for
     * OSI-licensed open-source projects, education, and businesses under 250
     * seats/$1M revenue; above that it requires a paid licence.
     */
    public function license(): string
    {
        return 'Proprietary (freemium)';
    }

    public function initialReleaseYear(): int
    {
        return 2025;
    }

    public function parser(): string
    {
        return 'own (C#/.NET compiled to WASM)';
    }

    /**
     * The CLI wrapper's own numbering, which is not the PHP Tools version the
     * Latest release column carries — phpy 0.2.0 wraps engine 1.0.18519.
     */
    protected function versionPattern(): ?string
    {
        return '/phpy\s+(\d+\.\d+\.\d+)/i';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://blog.devsense.com/2025/update-1-58-benchmarks/',
            'phpy: a proof-of-concept CLI',
        );
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::npm('phpy');
    }
}
