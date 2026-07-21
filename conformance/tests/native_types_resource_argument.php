<?php

declare(strict_types=1);

namespace Conformance\Tests\NativeTypesResourceArgument;

/**
 * `resource` is not a valid native type declaration.
 *
 * References:
 * - PHP native type declarations
 * - PHP native scalar parameter types
 * - legacy PHP resource values
 */

function takesResource(resource $value): void // E: resource is not a supported native parameter type
{
}

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

function takesBool(bool $value): void
{
}

$handle = fopen('php://memory', 'r');
if ($handle === false) {
    throw new \RuntimeException('failed to open memory stream');
}

takesResource($handle); // E?: resource-typed parameters should also be rejected at call sites
takesString($handle); // E: resource is not accepted by string parameter
takesInt($handle); // E: resource is not accepted by int parameter
takesBool($handle); // E: resource is not accepted by bool parameter
