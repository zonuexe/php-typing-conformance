<?php

declare(strict_types=1);

namespace Conformance\Tests\ObjectsCloneWithProperties;

/**
 * PHP 8.5 `clone($object, array $withProperties)` copies an object and writes
 * the given properties on the copy.
 *
 * The result is the same class as the original. Literal keys must name an
 * accessible property, and each value must match that property's type. The
 * unary `clone $object` form is the control: a diagnostic there means the
 * reaction is about cloning in general, not the two-argument form.
 *
 * Source: vimeo/psalm#11893 (6.19.0).
 *
 * References:
 * - https://wiki.php.net/rfc/clone_with_v2
 * - https://github.com/vimeo/psalm/pull/11893
 */

final class Point
{
    public function __construct(
        public int $x,
        public string $label,
    ) {
    }
}

function takesPoint(Point $point): void
{
}

function takesInt(int $value): void
{
}

$point = new Point(1, 'origin');

$unary = clone $point;
takesPoint($unary); // V: unary clone keeps the class

$copy = clone($point, ['x' => 2]);
takesPoint($copy); // V: the two-argument form keeps the class
takesInt($copy->x); // V

clone($point, ['nope' => 1]); // E: Point has no $nope
clone($point, ['x' => 'str']); // E: $x is int, not string
