<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanImmutable;

/**
 * Cross-tool handling of class-level `@immutable`.
 *
 * PHPStan treats every property of the class as `@readonly`. Psalm's
 * `@psalm-immutable` (methods must not mutate `$this` either) is measured
 * separately.
 *
 * References:
 * - PHPStan phpdocs-basics: Immutable classes
 */

/**
 * @immutable
 */
final class Point // T: @immutable
{
    public function __construct(
        public string $label,
    ) {
    }
}

$point = new Point('origin'); // V
echo $point->label; // V
$point->label = 'moved'; // E?: immutable property assigned outside constructor
