<?php

declare(strict_types=1);

namespace Conformance\Metadata;

/**
 * A named individual, with the profile the name is verified against.
 *
 * Plain data: the name is text, the profile is a URL. Whether a row renders
 * either as a link is the template's business.
 */
final readonly class Person
{
    public function __construct(
        public string $name,
        public ?string $profileUrl = null,
    ) {
    }
}
