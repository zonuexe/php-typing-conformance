<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhpstanDumpPhpDocType;

/**
 * Cross-tool handling of `PHPStan\dumpPhpDocType()`.
 *
 * Like `dumpType()`, but dumps the PHPDoc-facing representation of the type.
 * Sibling of `debug_phpstan_dump_type`.
 *
 * References:
 * - PHPStan helpers: `\PHPStan\dumpPhpDocType()`
 *
 * @conformance-kind debug
 */

function example(int|string $value): void // T: PHPStan\dumpPhpDocType
{
    if (\is_int($value)) {
        \PHPStan\dumpPhpDocType($value); // E?: reports the PHPDoc type (int)
    }
}
