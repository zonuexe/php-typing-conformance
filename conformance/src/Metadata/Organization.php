<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use function sprintf;

/**
 * The entity currently behind a project.
 *
 * `name` is the entity's own name for a company and the GitHub org slug for a
 * community project; label() is where the two become one column's worth of
 * text. See [[OrganizationKind]] for what each case admits.
 */
final readonly class Organization
{
    private function __construct(
        public OrganizationKind $kind,
        public string $name,
        public ?string $url,
    ) {
    }

    public static function company(string $name, ?string $url = null): self
    {
        return new self(OrganizationKind::Company, $name, $url);
    }

    /**
     * @param string $githubOrg the org's own slug, e.g. `phan` for github.com/phan
     */
    public static function community(string $githubOrg, string $url): self
    {
        return new self(OrganizationKind::Community, $githubOrg, $url);
    }

    public static function personal(): self
    {
        return new self(OrganizationKind::Personal, '', null);
    }

    public function label(): string
    {
        return match ($this->kind) {
            OrganizationKind::Company => $this->name,
            OrganizationKind::Community => sprintf('Community (%s)', $this->name),
            OrganizationKind::Personal => 'Personal',
        };
    }
}
