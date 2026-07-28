<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackNonEmptyScalar;

/**
 * `non-empty-scalar`
 *
 * `scalar` with every falsy value subtracted, which is a wider cut than the
 * name suggests: `0`, `0.0`, `''`, `'0'` and `false` all go, so the spelling
 * removes values from all four members of the union rather than emptying one.
 * Analyzers that model it reject them; others fall back to `scalar` or `mixed`
 * and accept them.
 *
 * The subtraction does not survive contact with PHP's numeric widening. What
 * PHPStan resolves is `float|int<min, -1>|int<1, max>|non-falsy-string|true`:
 * the `float` member is never narrowed, and an `int` is accepted wherever a
 * `float` is expected, so `0` and `0.0` reach the parameter through it even
 * though both are falsy. Those two probes are expected to stay silent in the
 * reference implementation; the string and bool probes are the ones the
 * subtraction actually holds on to.
 *
 * References:
 * - PHPStan TypeNodeResolver `non-empty-scalar` resolves to int|float|string|bool minus StaticTypeFactory::falsey()
 */

/**
 * @return non-empty-scalar
 */
function returnsNonEmptyScalar() // T: non-empty-scalar
{
    $values = [1, -1, 1.5, 'x', true];

    return $values[\array_rand($values)];
}

/**
 * @param non-empty-scalar $value
 */
function acceptsNonEmptyScalar($value): void // T: non-empty-scalar
{
}

acceptsNonEmptyScalar(returnsNonEmptyScalar());

// Truthy values of every member satisfy the parameter.
acceptsNonEmptyScalar(1);
acceptsNonEmptyScalar(1.5);
acceptsNonEmptyScalar('x');
acceptsNonEmptyScalar(true);

// The falsy value of each member is subtracted.
acceptsNonEmptyScalar(0); // E?: 0 is falsy, so it is not a non-empty-scalar
acceptsNonEmptyScalar(0.0); // E?: 0.0 is falsy, so it is not a non-empty-scalar
acceptsNonEmptyScalar(''); // E?: '' is falsy, so it is not a non-empty-scalar
acceptsNonEmptyScalar(false); // E?: false is falsy, so it is not a non-empty-scalar

// Including the string PHP considers falsy despite its length.
acceptsNonEmptyScalar('0'); // E?: '0' is falsy, so it is not a non-empty-scalar
