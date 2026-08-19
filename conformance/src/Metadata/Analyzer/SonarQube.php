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
use function sprintf;

/**
 * SonarQube Community Build, measured through the bundled sonar-php plugin.
 *
 * The scanner talks to a server; the PHP analysis itself is
 * github.com/SonarSource/sonar-php. Filed as an inferring linter: the engine
 * exists so a rule catalogue (empty methods, unused parameters, insecure
 * `rand()`, …) can see a little of the code, not so the product can be a
 * type-correctness oracle. A scan of this corpus produced no argument-type
 * or return-type findings.
 */
final class SonarQube extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'SonarQube';
    }

    public function homepage(): string
    {
        return 'https://www.sonarsource.com/products/sonarqube/';
    }

    public function analysis(): AnalysisKind
    {
        return AnalysisKind::InferringLinter;
    }

    public function interfaces(): array
    {
        return [InterfaceKind::Cli];
    }

    public function bundled(): array
    {
        return [];
    }

    public function languages(): array
    {
        return ['Java'];
    }

    public function founders(): array
    {
        return [
            new Person('Olivier Gaudin', 'https://www.sonarsource.com/blog/sonars-17-year-anniversary/'),
            new Person('Freddy Mallet', 'https://github.com/fmallet'),
            new Person('Simon Brandhof', 'https://www.sonarsource.com/blog/sonars-17-year-anniversary/'),
        ];
    }

    public function organization(): Organization
    {
        return Organization::company('SonarSource', 'https://www.sonarsource.com');
    }

    public function lead(): ?LeadMaintainer
    {
        return null;
    }

    public function license(): string
    {
        return 'LGPL-3.0 (Community Build); sonar-php SSALv1';
    }

    /**
     * The PHP plugin's first releases, not the 2008 Sonar platform.
     * sonar-php's copyright line starts in 2010.
     */
    public function initialReleaseYear(): int
    {
        return 2010;
    }

    public function parser(): string
    {
        return 'SSLR PHP parser';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://github.com/SonarSource/sonar-php',
            'sonar-php (SonarSource)',
        );
    }

    /**
     * The banner is `SonarQube <server> (php <plugin>)`. The plugin version
     * is what this column actually measures.
     */
    protected function versionPattern(): ?string
    {
        return '/php\s+(\d+\.\d+\.\d+)/i';
    }

    public function releaseUrl(string $version): ?string
    {
        return sprintf('https://github.com/SonarSource/sonar-php/releases/tag/%s', $version);
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('SonarSource/sonar-php');
    }
}
