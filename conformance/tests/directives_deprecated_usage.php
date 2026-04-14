<?php

declare(strict_types=1);

namespace Conformance\Tests\DirectivesDeprecatedUsage;

/**
 * Deprecated symbol usage through companion support files.
 *
 * References:
 * - python-typing directives_deprecated inspiration
 * - PHP analyzer support for @deprecated on functions and classes
 */

$message = oldMessage(); // E?: deprecated function usage
$formatter = new LegacyFormatter(); // E?: deprecated class usage
echo $formatter->format($message);
