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
 * error as you type in PhpStorm.
 *
 * This column measures it through PhpStorm, using the IDE's own "Run Qodana
 * in the IDE" action, documented at
 * https://www.jetbrains.com/help/qodana/quick-start.html#quickstart-run-in-ide
 * — not through https://github.com/JetBrains/qodana-cli, which is a separate
 * artifact on a separate release cadence and reports to Qodana Cloud.
 *
 * The founder column is the one this row cannot answer the way the others do.
 * Everywhere else it names someone who started a project and links the profile
 * the name was verified against; here the artifact is a 2021 JetBrains product
 * built by a team inside a company founded in 2000, and no individual founded
 * it in that sense.
 *
 * What can be sourced is one step removed, and the column should be read as
 * that and nothing more: the engine is PhpStorm's, and the name recorded is
 * the person who led PhpStorm. Lead on the IDE whose inspections are measured
 * is not the same claim as founder of the 2021 product that packages them.
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

    /**
     * The profile is the verification: it gives the name, JetBrains as the
     * employer, and jetbrains.com/phpstorm as the site, so the person and the
     * product are tied together by the person themselves. How the name was
     * arrived at, and who else was considered, is written up in
     * docs/ja/jetbrains-product-founders.md.
     */
    public function founders(): array
    {
        return [new Person('Alexey Gopachenko', 'https://github.com/neuro159')];
    }

    // founderEmployer() is left unanswered on purpose. It exists to say that a
    // founder built the thing somewhere other than where the project now lives
    // — Vimeo for Psalm, VK for NoVerify. Here the employer and the
    // organization are both JetBrains, so stating it twice adds nothing.

    public function organization(): Organization
    {
        return Organization::company('JetBrains', 'https://www.jetbrains.com');
    }

    public function lead(): ?LeadMaintainer
    {
        return null;
    }

    /**
     * Not freemium, unlike Intelephense, which this row otherwise resembles.
     * There is no free tier on the path measured here: running the inspections
     * from the IDE needs a commercial PhpStorm licence whether or not any
     * individual inspection is an Ultimate-only feature. qodana-cli has a free
     * Qodana Cloud tier, but that is the artifact this column does not use.
     */
    public function license(): string
    {
        return 'Proprietary';
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
     * version the CI container, whereas this column runs Qodana from inside
     * the IDE. The service reports the build number a report states, so the
     * two can be compared directly.
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
