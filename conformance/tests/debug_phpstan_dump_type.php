<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhpstanDumpType;

/**
 * Cross-tool handling of `PHPStan\dumpType()`.
 *
 * PHPStan's type-inspection helper: calling it reports the inferred type of
 * the argument as a diagnostic. Other analyzers typically treat it as an
 * undefined function.
 *
 * References:
 * - PHPStan troubleshooting / playground helpers: `\PHPStan\dumpType()`
 *
 * @conformance-kind debug
 */

function example(int|string $value): void // T: PHPStan\dumpType
{
    if (\is_int($value)) {
        // Narrowed to int: native dump reports that.
        \PHPStan\dumpType($value); // E?: reports the inferred type (int)
    }
}
