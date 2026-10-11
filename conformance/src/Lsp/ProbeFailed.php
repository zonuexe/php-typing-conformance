<?php

declare(strict_types=1);

namespace Conformance\Lsp;

use RuntimeException;

/**
 * One probe's request was answered with an error. The probe is recorded as
 * failed and the session goes on; a broken connection is a RuntimeException
 * and ends it.
 */
final class ProbeFailed extends RuntimeException
{
}
