<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackValueOfTemplateEnum;

/**
 * `value-of<T>` over a template parameter bound to a backed enum
 *
 * The generic spelling of `->value`: the function takes `T of Suit` and
 * declares `value-of<T>` as its return, so the backing-scalar projection is
 * evaluated from whatever case type T is inferred as. Passing a single known
 * case is the precision probe — an exact tool returns `'hearts'` for
 * `Suit::Hearts`, one that stops at the bound returns every backing value,
 * and one that never evaluates the projection returns `mixed` and satisfies
 * the int probe in silence.
 *
 * References:
 * - PHPStan phpdoc-types: value-of<BackedEnum> over templates
 * - Psalm utility_types.md: value-of on backed enums
 */

enum Suit: string
{
    case Hearts = 'hearts';
    case Spades = 'spades';
}

/**
 * @template T of Suit
 * @param T $case
 * @return value-of<T>
 */
function backingValue(Suit $case) // T: value-of<T>
{
    // A tool can evaluate the projection at call sites and still be unable to
    // prove this body produces value-of<T>; that limitation surfaces as a
    // false positive here, not as an expectation of the test.
    return $case->value; // V
}

function acceptsString(string $value): void
{
}

function acceptsInt(int $value): void
{
}

/**
 * @param 'hearts' $value
 */
function acceptsHearts(string $value): void
{
}

// The backing value of any Suit case is a string.
acceptsString(backingValue(Suit::Hearts)); // V

// With T inferred as the single case, the projection is that case's literal
// backing value: a tool that only reaches the bound reports a false positive.
acceptsHearts(backingValue(Suit::Hearts)); // V

// The backing values of Suit are strings, never ints.
acceptsInt(backingValue(Suit::Spades)); // E?: Suit backs its cases with strings, not ints
