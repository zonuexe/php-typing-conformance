<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmReadonly;

/**
 * Cross-tool handling of `@psalm-readonly` / `@readonly` on properties.
 *
 * Write allowed in the constructor; forbidden afterwards (including outside).
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-readonly` and `@readonly`
 */

final class Label
{
    /** @psalm-readonly */
    public string $text; // T: @psalm-readonly

    public function __construct(string $text)
    {
        $this->text = $text;
    }
}

$label = new Label('ok');
$label->text = 'no'; // E?: @psalm-readonly property assigned outside constructor
