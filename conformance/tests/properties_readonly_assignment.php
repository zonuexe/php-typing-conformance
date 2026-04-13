<?php

declare(strict_types=1);

/**
 * Basic readonly property write checks.
 *
 * References:
 * - PHP readonly properties
 */

final class ReadonlyBox
{
    public function __construct(
        public readonly int $value,
    ) {
    }
}

$box = new ReadonlyBox(1);
$box->value = 2; // E: readonly property cannot be reassigned
