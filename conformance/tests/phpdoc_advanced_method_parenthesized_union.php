<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedMethodParenthesizedUnion;

/**
 * Parenthesized union types in `@method` parameters.
 *
 * `@method void takeUnion((int|string) $x)` should accept `int` and `string`
 * and reject `bool`. A tool that cannot parse the parentheses drops the
 * magic method or reads the parameter as `mixed`.
 *
 * Source: vimeo/psalm#12057 (6.19.2).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/12057
 * - phpdoc_advanced_member_tag_undefined_type for `@method` with a missing class
 */

/**
 * @method void takeUnion((int|string) $x)
 * @method void takeInt(int $x)
 */
final class Magic // T: @method
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        return null;
    }
}

$magic = new Magic();
$magic->takeUnion(1); // V
$magic->takeUnion('x'); // V
$magic->takeUnion(true); // E?: bool is not int|string
$magic->takeInt(1); // V
$magic->takeInt('x'); // E: takeInt wants int
