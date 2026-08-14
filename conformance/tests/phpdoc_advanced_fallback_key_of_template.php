<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFallbackKeyOfTemplate;

/**
 * `key-of<T>` over a template parameter
 *
 * The operand is neither a shape nor a constant but a template parameter: a
 * generic function takes `T of array` and returns one of its keys. Evaluating
 * the return type now happens at the *call site*, after T has been inferred
 * from the argument — two features composed, and the composition is what the
 * probes separate. A tool can evaluate `key-of` over a written-out shape and
 * still return `array-key` here, and a tool that infers T but widens the
 * argument's keys to `string` loses the literal union the caller relied on.
 *
 * References:
 * - PHPStan phpdoc-types: key-of over template parameters
 * - Psalm utility_types.md: key-of with templates
 */

/**
 * @template T of array<array-key, mixed>
 * @param T $items
 * @return key-of<T>
 */
function firstKey(array $items) // T: key-of<T>
{
    foreach ($items as $key => $_) {
        // A tool can evaluate the projection at call sites and still be unable
        // to prove this body produces key-of<T>; that limitation surfaces as a
        // false positive here, not as an expectation of the test.
        return $key; // V
    }

    throw new \InvalidArgumentException('empty array');
}

function acceptsString(string $value): void
{
}

function acceptsInt(int $value): void
{
}

/**
 * @param 'debug'|'verbose' $flag
 */
function acceptsFlagName(string $flag): void
{
}

// With string keys, the returned key is a string.
acceptsString(firstKey(['debug' => false, 'verbose' => true])); // V

// More precisely, it is the union of this argument's keys: a tool that infers
// T but widens the keys to `string` reports a false positive right here.
acceptsFlagName(firstKey(['debug' => false, 'verbose' => true])); // V

// A key of this argument is never an int.
acceptsInt(firstKey(['debug' => false, 'verbose' => true])); // E?: the keys of this argument are strings
