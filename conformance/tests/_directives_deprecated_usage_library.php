<?php

declare(strict_types=1);

namespace Conformance\Tests\DirectivesDeprecatedUsage;

/**
 * @deprecated Use newMessage() instead.
 */
function oldMessage(): string
{
    return 'legacy';
}

/**
 * @deprecated Use ModernFormatter instead.
 */
final class LegacyFormatter
{
    public function format(string $value): string
    {
        return strtoupper($value);
    }
}
