<?php

declare(strict_types=1);

/**
 * Basic PHPDoc-only parameter type checks.
 *
 * References:
 * - phpDocumentor PHPDoc @param
 * - de facto analyzer support for PHPDoc parameter types
 */

/**
 * @param int $value
 */
function takesDocblockInt($value): void
{
}

takesDocblockInt(1);
takesDocblockInt('x'); // E: string is not accepted by @param int
