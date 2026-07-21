<?php

declare(strict_types=1);

namespace Conformance\Tests\ExceptionsThrowsDocblock;

/**
 * Basic @throws compatibility checks.
 *
 * References:
 * - phpDocumentor @throws
 *
 * @conformance-kind style
 */

final class CustomException extends \RuntimeException
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
 * @throws \RuntimeException
 */
function documentedAsRuntime(): void
{
    alwaysThrows();
}
