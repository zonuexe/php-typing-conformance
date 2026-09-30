<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedStringPseudoIntersection;

/**
 * `non-empty-string&lowercase-string`
 *
 * The intersection of two string refinements, written with `&` rather than as
 * the fused spelling `non-empty-lowercase-string`. Psalm 6.19 collapses the
 * intersection to that atomic; PHPStan already models it as the same pair of
 * accessories. Analyzers that still require intersection members to be objects
 * reject the spelling at the declaration.
 *
 * `''` fails only the non-empty half and `'ABC'` only the lowercase half, so
 * an analyzer can model one and miss the other. The fused spelling is already
 * measured in `phpdoc_advanced_fallback_non_empty_lowercase_string`.
 *
 * Source: vimeo/psalm#11823 (6.19.0), from Laravel `Str::lower()`.
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/11823
 * - PHPStan TypeNodeResolver `non-empty-lowercase-string`
 */

/**
 * @param non-empty-string&lowercase-string $value
 */
function acceptsIntersection($value): void // T: non-empty-string&lowercase-string
{
}

/**
 * @param non-empty-lowercase-string $value
 */
function acceptsFused($value): void // T: non-empty-lowercase-string
{
}

acceptsIntersection('abc'); // V
acceptsFused('abc'); // V: the fused spelling admits the same value

acceptsIntersection(''); // E?: '' is empty
acceptsIntersection('ABC'); // E?: 'ABC' is not lowercase
