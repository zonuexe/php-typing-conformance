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
 * Phpactor.
 *
 * One sourcing caveat worth keeping: Dan Leech's GitHub profile sets its name
 * field to the handle "dantleech" rather than to a name, so the "Dan Leech"
 * spelling comes from the personal blog that profile links to, not from the
 * profile itself. The announcement is that blog's "Three Years of Phpactor" —
 * a retrospective rather than a launch post, which is the closest first-party
 * equivalent the project has, the same latitude Phan's record already takes
 * with a conference talk.
 */
final class Phpactor extends LanguageServerMetadata
{
    public function name(): string
    {
        return 'Phpactor';
    }

    public function url(): string
    {
        return 'https://phpactor.readthedocs.io/en/master/';
    }

    public function diagnostics(): DiagnosticsSource
    {
        return DiagnosticsSource::OwnEngineAndAdapter;
    }

    public function diagnosticsNote(): ?string
    {
        return 'Ships its own Worse Reflection inference and eleven built-in diagnostic providers; the LSP support matrix additionally advertises “support for integrating with phpstan, Psalm and php-cs-fixer”.';
    }

    public function analyzersDriven(): array
    {
        return ['PHPStan', 'Psalm'];
    }

    public function analyzersDrivenNote(): ?string
    {
        return 'Opt-in extension packages, not core: phpactor/language-server-phpstan-extension and phpactor/language-server-psalm-extension. php-cs-fixer and PHP_CodeSniffer integrate the same way, for formatting rather than type diagnostics.';
    }

    public function bundled(): string
    {
        return 'Refactorings, code generation, VIM plugin';
    }

    public function languages(): array
    {
        return ['PHP'];
    }

    public function founders(): array
    {
        return [new Person('Dan Leech', 'https://github.com/dantleech')];
    }

    public function organization(): Organization
    {
        return Organization::community('phpactor', 'https://github.com/phpactor');
    }

    public function lead(): ?LeadMaintainer
    {
        return new LeadMaintainer('Dan Leech');
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
        return 'tolerant-php-parser (fork)';
    }

    public function parserNote(): ?string
    {
        return 'A fork of Microsoft’s tolerant-php-parser maintained under the Phpactor org; the founder’s blog says nikic/PHP-Parser was tried first and dropped because the tolerant parser “was designed exactly for Phpactor’s use case”.';
    }

    public function announcement(): ?Announcement
    {
        return new Announcement(
            'https://www.dantleech.com/blog/2018/08/19/three-years-of-phpactor/',
            'Three Years of Phpactor',
        );
    }
}
