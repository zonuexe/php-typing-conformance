<?php

declare(strict_types=1);

namespace Conformance\Metadata;

use InvalidArgumentException;
use function preg_match;
use function sprintf;

/**
 * One upstream release: what it was called, and when it shipped.
 *
 * The only part of an analyzer's metadata that goes stale on its own, which is
 * why it is injected rather than hardcoded alongside the curated facts. See
 * [[ReleaseTable]] for where the values come from.
 */
final readonly class Release
{
    /**
     * @param string $version as upstream names it — `unversioned` where a
     *                        project ships without version numbers
     * @param string $date    ISO 8601 calendar date, YYYY-MM-DD; empty where
     *                        no date is known — the version this suite
     *                        evaluated is parsed out of the tool's own
     *                        banner, which carries no date
     */
    public function __construct(
        public string $version,
        public string $date = '',
    ) {
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw new InvalidArgumentException(sprintf('Release date must be YYYY-MM-DD, got: %s', $date));
        }
    }
}
