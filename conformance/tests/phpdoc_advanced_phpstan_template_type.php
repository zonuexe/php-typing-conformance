<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPhpstanTemplateType;

/**
 * Cross-tool handling of the `template-type` utility.
 *
 * `template-type<Object, ClassName, 'TName'>` extracts a named `@template`
 * argument from an object type. PHPStan-specific utility for generics.
 *
 * References:
 * - PHPStan phpdoc-types: Utility types for generics (`template-type`)
 */

/**
 * @template T
 */
final class Box
{
    /** @param T $value */
    public function __construct(
        public mixed $value,
    ) {
    }
}

/**
 * @template T
 * @param Box<T> $box
 * @return template-type<Box<T>, Box, 'T'>
 */
function unwrap(Box $box): mixed // T: template-type<Box<T>, Box, 'T'>
{
    return $box->value;
}

function takesString(string $value): void
{
}

// T resolves to string.
takesString(unwrap(new Box('ok')));

// T resolves to int, so string is wrong.
takesString(unwrap(new Box(1))); // E?: template-type should yield int here
