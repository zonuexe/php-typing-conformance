<?php

declare(strict_types=1);

/**
 * Basic @throws compatibility checks.
 *
 * References:
 * - phpDocumentor @throws
 */

final class CustomException extends RuntimeException
{
}

/**
 * @throws CustomException
 */
function alwaysThrows(): never
{
    throw new CustomException('boom');
}

/**
 * @throws RuntimeException
 */
function documentedAsRuntime(): void
{
    alwaysThrows();
}
