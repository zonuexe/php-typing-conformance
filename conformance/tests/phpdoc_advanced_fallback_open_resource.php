<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackOpenResource;

/**
 * `open-resource`
 *
 * A resource handle that has not been closed. PHPStan resolves the spelling to
 * a plain ResourceType — the same type as `resource` and `closed-resource` —
 * so it accepts the word without modelling the distinction the word is for.
 * The probe is a handle used after `fclose()`, which separates an analyzer that
 * tracks the state of a handle from one that only checks it is a resource.
 *
 * References:
 * - PHPStan TypeNodeResolver `open-resource` and `closed-resource` share one case, both resolving to ResourceType
 */

/**
 * @param open-resource $value
 */
function acceptsOpenResource($value): void // T: open-resource
{
}

$handle = \fopen('php://memory', 'r');
\assert($handle !== false);

// A freshly opened handle satisfies the parameter.
acceptsOpenResource($handle);

// A non-resource does not.
acceptsOpenResource('not a resource'); // E?: a string is not an open-resource

$closed = \fopen('php://memory', 'r');
\assert($closed !== false);
\fclose($closed);

// Still a resource, no longer open.
acceptsOpenResource($closed); // E?: a closed handle is not an open-resource
