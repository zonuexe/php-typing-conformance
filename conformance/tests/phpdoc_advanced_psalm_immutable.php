<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmImmutable;

/**
 * Cross-tool handling of `@psalm-immutable`.
 *
 * Class-level immutability: public properties behave as readonly for consumers
 * and non-constructor methods must not mutate `$this`.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-immutable`
 */

/**
 * @psalm-immutable
 */
final class Point // T: @psalm-immutable
{
    public function __construct(
        public string $label,
    ) {
    }

    public function relabel(string $label): void
    {
        $this->label = $label; // E?: mutation inside immutable class method
    }
}

$point = new Point('origin');
$point->label = 'moved'; // E?: immutable property assigned outside constructor
