<?php

declare(strict_types=1);

namespace Conformance\Metadata;

/**
 * The individual currently driving day-to-day development.
 *
 * Usually the founder, and then `profileUrl` stays null: the Founder column
 * already links that person, and a second link to the same profile would be
 * noise. It is set only where the lead verifiably differs from the founder,
 * and `note` says on what evidence — so a succession is stated rather than
 * silently implied by a repeated name.
 */
final readonly class LeadMaintainer
{
    public function __construct(
        public string $name,
        public ?string $profileUrl = null,
        public ?string $note = null,
    ) {
    }
}
