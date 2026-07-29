<?php

declare(strict_types=1);

namespace Conformance\Metadata\Analyzer;

use Conformance\Metadata\AnalysisKind;
use Conformance\Metadata\Announcement;
use Conformance\Metadata\AnalyzerMetadata;
use Conformance\Metadata\InterfaceKind;
use Conformance\Metadata\LeadMaintainer;
use Conformance\Metadata\Organization;
use Conformance\Metadata\Person;
use Conformance\Metadata\ReleaseFeed;

/**
 * Qodana is the packaging, not the analysis. What actually inspects the code
 * is the PHP plugin's inspection engine — the same one that underlines a type
 * error as you type in PhpStorm — and this column measures it through the
 * IDE's own Inspect Code, because the CLI linter is licensed separately.
 *
 * That makes the founder question awkward to answer honestly. The engine has
 * no founder in the sense the other rows use the word: it is a JetBrains
 * product that predates Qodana by more than a decade, built by a team. The
 * name recorded is the one JetBrains itself credits with starting PhpStorm.
 */
final class Qodana extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'Qodana';
    }

    public function homepage(): string
    {
        return 'https://www.jetbrains.com/qodana/';
    }

    /**
     * The engine is an IDE's, and it answers accordingly: alongside the type
     * checks this suite measures it reports unused symbols, style and naming,
     * each with a quick fix attached.
     */
    public function analysis(): AnalysisKind
    {
        return AnalysisKind::CodeIntelligence;
    }

    public function interfaces(): array
    {
        return [InterfaceKind::Ide, InterfaceKind::Cli];
    }

    public function bundled(): array
    {
        return ['quick fixes', 'formatter', 'refactoring'];
    }

    public function languages(): array
    {
        return ['Java', 'Kotlin'];
    }

    public function founders(): array
    {
        return [new Person('Dmitry Jemerov', 'https://github.com/yole')];
    }

    public function founderEmployer(): ?string
    {
        return 'JetBrains';
    }

    public function organization(): Organization
    {
        return Organization::company('JetBrains', 'https://www.jetbrains.com');
    }

    public function lead(): ?LeadMaintainer
    {
        return null;
    }

    public function license(): string
    {
        return 'Proprietary (freemium)';
    }

    /** The year Qodana itself first shipped, not the year PhpStorm did. */
    public function initialReleaseYear(): int
    {
        return 2021;
    }

    public function parser(): string
    {
        return 'IntelliJ PSI';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://blog.jetbrains.com/idea/2021/02/early-access-program-for-qodana-a-new-product-that-brings-the-smarts-of-jetbrains-ides-into-your-ci-pipeline/',
            'Qodana EAP opens (JetBrains blog)',
        );
    }

    /**
     * PhpStorm's own release service, not a package registry, and not the
     * `qodana-cli` tags that qodana.yaml's `linter:` field follows: those
     * version the CI container, whereas this column measures Inspect Code
     * inside the IDE. The service reports the build number a report states,
     * so the two can be compared directly.
     */
    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::jetBrains('PS');
    }

    /**
     * The banner is the product name and an IDE build number: "Qodana
     * 262.8665.325", which is PhpStorm 2026.2.0.1. Three components like a
     * semantic version, but it is a build number and does not shorten
     * further.
     */
    protected function versionPattern(): ?string
    {
        return '/qodana\s+(\d+\.\d+\.\d+)/i';
    }
}
