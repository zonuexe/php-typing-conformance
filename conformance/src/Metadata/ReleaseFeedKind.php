<?php

declare(strict_types=1);

namespace Conformance\Metadata;

/**
 * Where a project publishes the releases this suite tracks.
 *
 * One case per registry the tools actually use. A project can appear in
 * several -- Steins is on GitHub and Packagist -- and the case chosen is the
 * one this suite installs from, so "up to date" means the same thing in the
 * report as it does in vendor-bin.
 */
enum ReleaseFeedKind
{
    case GitHub;
    case Npm;
    case Packagist;

    /**
     * JetBrains publishes IDE builds through its own release service rather
     * than any package registry, and that service is the only place the exact
     * build a Qodana report names can be resolved to a date.
     */
    case JetBrains;
}
