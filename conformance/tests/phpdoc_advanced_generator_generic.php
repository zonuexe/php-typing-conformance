<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedGeneratorGeneric;

/**
 * Generic `Generator` value type in PHPDoc.
 *
 * A four-parameter `Generator<TKey, TValue, TSend, TReturn>` is widely
 * documented; this file only asks whether the yielded value (`TValue`) is
 * enforced when one generator is passed where another is required. Key, send,
 * and return channels are a different measurement.
 *
 * References:
 * - PHPStan generic classes and Generator
 * - Psalm generic objects and Generator
 */

/**
 * @return \Generator<int, string, void, bool>
 */
function strings(): \Generator // T: \Generator<int, string, void, bool>
{
    yield 'value';

    return true;
}

/**
 * @return \Generator<int, int, void, bool>
 */
function integers(): \Generator // T: \Generator<int, int, void, bool>
{
    yield 1;

    return true;
}

/**
 * @param \Generator<int, string, void, bool> $generator
 */
function takesStringGenerator(\Generator $generator): void // T: \Generator<int, string, void, bool>
{
}

takesStringGenerator(strings()); // V
takesStringGenerator(integers()); // E?: a Generator yielding integers is not a Generator yielding strings
