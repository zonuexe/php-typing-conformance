<?php

declare(strict_types=1);

namespace Conformance\Tests\PropertiesPromotedParamMismatch;

/**
 * Promoted property constructor parameter compatibility checks.
 *
 * References:
 * - PHP constructor property promotion
 */

final class IdBox
{
    public function __construct(
        public int $id,
    ) {
    }
}

$ok = new IdBox(1);
$bad = new IdBox('x'); // E: string is not accepted by promoted int property parameter
echo $ok->id;
