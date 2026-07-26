<?php

declare(strict_types=1);

namespace Conformance\Metadata;

/**
 * Where a project introduced itself, in its own words.
 *
 * First-party by preference; a conference talk or a retrospective counts where
 * a project never wrote a launch post, since the point is the project speaking
 * for itself rather than the genre of the post.
 */
final readonly class Announcement
{
    public function __construct(
        public string $url,
        public string $label,
    ) {
    }
}
