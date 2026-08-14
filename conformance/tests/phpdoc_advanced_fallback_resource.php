<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackResource;

/**
 * `resource`
 *
 * The one type in this group with no native spelling at all: PHP has never
 * allowed `resource` in a signature, so the PHPDoc form is the only way to say
 * it and there is no base type to fall back to. Analyzers that model it reject
 * a non-handle; others treat the word as a class name or as `mixed`.
 *
 * References:
 * - PHPStan TypeNodeResolver `resource` resolves to ResourceType, after trying a pseudo-type class
 */

/**
 * @param resource $value
 */
function acceptsResource($value): void // T: resource
{
}

$handle = \fopen('php://memory', 'r');
\assert($handle !== false);

// A stream handle satisfies the parameter.
acceptsResource($handle); // V

// Scalars and objects do not.
acceptsResource(123); // E?: an int is not a resource
acceptsResource('php://memory'); // E?: a string is not a resource
acceptsResource(new \stdClass()); // E?: an object is not a resource
