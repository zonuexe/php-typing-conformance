<?php

declare(strict_types=1);

namespace Conformance\Metadata;

/**
 * What kind of entity, if any, is behind a project.
 *
 * Three cases, so that the Organization column can state something positive
 * for every row. A bare dash would read as "not checked" once other rows carry
 * real values, which would undo the point of separating this from the lead
 * maintainer in the first place.
 */
enum OrganizationKind
{
    /**
     * A company, or the founder's own formalized entity — PHPStan s.r.o.,
     * Carthage Software, TypedDuck, VK.COM, Intelephense (whose footer carries
     * an Australian Business Number, so it is a registered business and not
     * just a product name). Verified via each entity's own page rather than
     * assumed from the name, and named as that page names itself rather than
     * by its URL slug.
     */
    case Company;

    /**
     * No company at all: a multi-contributor GitHub home with no single
     * commercial owner. Phan and Psalm — for Psalm the github.com/psalm
     * community-packages org, not vimeo/psalm itself.
     */
    case Community;

    /**
     * One person's project, with no separate entity, formal or informal. The
     * same information the lead maintainer already carries, restated so the
     * column never falls back to an ambiguous dash.
     */
    case Personal;
}
