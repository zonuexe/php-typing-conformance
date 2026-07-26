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
use function sprintf;

final class PhpStan extends AnalyzerMetadata
{
    public function name(): string
    {
        return 'PHPStan';
    }

    /** Its README and composer.json read "PHPStan - PHP Static Analysis Tool" outright. */
    public function expansion(): ?string
    {
        return 'PHP Static Analysis Tool';
    }

    public function homepage(): string
    {
        return 'https://phpstan.org';
    }

    public function analysis(): AnalysisKind
    {
        return AnalysisKind::TypeChecker;
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
        return ['PHP'];
    }

    public function founders(): array
    {
        return [new Person('Ondřej Mirtes', 'https://github.com/ondrejmirtes')];
    }

    public function organization(): Organization
    {
        return Organization::company('PHPStan s.r.o.', 'https://github.com/phpstan');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Ondřej Mirtes');
    }

    public function license(): string
    {
        return 'MIT';
    }

    public function initialReleaseYear(): int
    {
        return 2016;
    }

    public function parser(): string
    {
        return 'nikic/PHP-Parser';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://phpstan.org/blog/find-bugs-in-your-code-without-writing-tests',
            'Find Bugs Without Tests',
        );
    }

    protected function versionPattern(): ?string
    {
        return '/(\d+\.\d+\.\d+)$/';
    }

    /**
     * The banner ends with the number -- "PHPStan - PHP Static Analysis Tool
     * 2.2.5" -- so the pattern anchors to the end rather than to a name.
     */
    public function releaseUrl(string $version): ?string
    {
        return sprintf('https://github.com/phpstan/phpstan/releases/tag/%s', $version);
    }

    public function releaseFeed(): ?ReleaseFeed
    {
        return ReleaseFeed::gitHub('phpstan/phpstan');
    }
}
