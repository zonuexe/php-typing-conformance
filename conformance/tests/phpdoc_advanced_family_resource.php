<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyResource;

/**
 * `resource`, `open-resource` and `closed-resource` PHPDoc types.
 *
 * These describe a PHP resource handle (open or closed). Analyzers that model
 * them reject a non-resource argument; others fall back and may accept it.
 * `resource` is not a valid native type, only a PHPDoc one.
 *
 * References:
 * - PHPStan TypeNodeResolver resource / open-resource / closed-resource
 */

/** @param resource $value */
function acceptsResource($value): void
{
}

/** @param open-resource $value */
function acceptsOpenResource($value): void
{
}

$handle = fopen('php://memory', 'r');
assert($handle !== false);

acceptsResource($handle);
acceptsResource(123); // E?: an int is not a resource

acceptsOpenResource($handle);
acceptsOpenResource('not a resource'); // E?: a string is not an open-resource
