<?php

declare(strict_types=1);

/**
 * Basic array shape required-key checks.
 *
 * References:
 * - PHPStan array shape syntax
 * - Psalm array shape syntax
 */

/**
 * @param array{host: string, port: int} $config
 */
function takesConfig(array $config): void
{
}

takesConfig(['host' => 'localhost', 'port' => 3306]);

takesConfig(['host' => 'localhost']); // E: required shape key is missing
