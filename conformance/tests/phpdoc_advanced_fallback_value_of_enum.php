<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackValueOfEnum;

/**
 * `value-of<T>` with a backed-enum operand
 *
 * With an enum as the operand, `value-of` means the union of the *backing*
 * values — `'hearts'|'spades'` here, not the cases themselves. That inverts
 * the usual direction of enum typing: the type holds the scalars, so a case
 * object is exactly what it excludes, and a tool has to know enums well enough
 * to walk them and still keep the two layers apart.
 *
 * References:
 * - PHPStan phpdoc-types: value-of<BackedEnum>
 * - Psalm utility_types.md: value-of on backed enums
 */

enum Suit: string
{
    case Hearts = 'hearts';
    case Spades = 'spades';
}

/**
 * @return value-of<Suit>
 */
function returnsSuitValue() // T: value-of<Suit>
{
    return \random_int(0, 1) === 1 ? 'hearts' : 'spades'; // V
}

function acceptsString(string $value): void
{
}

/**
 * @param value-of<Suit> $value
 */
function acceptsSuitValue($value): void // T: value-of<Suit>
{
}

// Every backing value of the enum is a string.
acceptsString(returnsSuitValue()); // V

// Both backing values satisfy the parameter.
acceptsSuitValue('hearts'); // V
acceptsSuitValue('spades'); // V

// A string that backs no case does not.
acceptsSuitValue('clubs'); // E?: 'clubs' backs no case of Suit

// Neither does the case object itself: the type is the backing values, not
// the cases.
acceptsSuitValue(Suit::Hearts); // E?: a case object is not one of its backing values
