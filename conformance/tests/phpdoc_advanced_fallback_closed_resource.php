<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackClosedResource;

/**
 * `closed-resource`
 *
 * The mirror of `open-resource`, and in PHPStan literally the same type: both
 * spellings share one `case` and resolve to ResourceType, so the analyzer that
 * defines the vocabulary does not itself distinguish them. This file records
 * whether anyone else does, by offering an open handle where a closed one is
 * required.
 *
 * References:
 * - PHPStan TypeNodeResolver `open-resource` and `closed-resource` share one case, both resolving to ResourceType
 */

/**
 * @param closed-resource $value
 */
function acceptsClosedResource($value): void // T: closed-resource
{
}

$closed = \fopen('php://memory', 'r');
\assert($closed !== false);
\fclose($closed);

// A closed handle satisfies the parameter.
acceptsClosedResource($closed);

// A non-resource does not.
acceptsClosedResource('not a resource'); // E?: a string is not a closed-resource

$open = \fopen('php://memory', 'r');
\assert($open !== false);

// Still a resource, not yet closed.
acceptsClosedResource($open); // E?: an open handle is not a closed-resource
