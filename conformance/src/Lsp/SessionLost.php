<?php

declare(strict_types=1);

namespace Conformance\Lsp;

use RuntimeException;

/**
 * PhpStorm's MCP server no longer knows the session a request named; see
 * {@see PhpStormMcpClient}.
 */
final class SessionLost extends RuntimeException
{
}
