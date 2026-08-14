<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedIntRangeKeyword;

/**
 * `int-range<0, 255>` (Phan spelling)
 *
 * Phan writes integer ranges as `int-range<min, max>`, while PHPStan, Psalm and
 * Mago use `int<min, max>`. Each side enforces its own spelling and does not
 * recognize the other, so this records who models the hyphenated keyword.
 *
 * References:
 * - Phan Type.php `int-range<min, max>` resolves to IntRangeType
 */

/**
 * @param int-range<0, 255> $value
 */
function acceptsByte($value): void // T: int-range<0, 255>
{
}

// An in-range literal satisfies the parameter for analyzers that model it.
acceptsByte(200); // V

// An out-of-range literal: analyzers that model `int-range` reject it, others
// do not recognize the keyword.
acceptsByte(256); // E?: 256 is above the int-range<0, 255> bounds
