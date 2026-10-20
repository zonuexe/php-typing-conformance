<?php

declare(strict_types=1);

namespace Conformance\Tests\GenericsGeneratorsSendType;

/**
 * `Generator` TSend, and foreach over a generator that requires `send()`.
 *
 * `phpdoc_advanced_generator_generic` measures TValue;
 * `generics_generator_get_return` measures TReturn. This file measures
 * TSend: `send()` rejects the wrong type, and iterating a generator whose
 * send type is non-nullable is unsafe because foreach never sends.
 *
 * Source: vimeo/psalm#10697 (5.23.0).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/10697
 * - phpdoc_advanced_generator_generic
 * - generics_generator_get_return
 */

/**
 * @return \Generator<int, string, void, void>
 */
function voidSend(): \Generator // T: \Generator<int, string, void, void>
{
    yield 0 => 'a';
}

/**
 * @return \Generator<int, string, int, void>
 */
function intSend(): \Generator // T: \Generator<int, string, int, void>
{
    yield 0 => 'a';
}

function iteratesVoidSend(): void
{
    foreach (voidSend() as $value) {
        echo $value; // V: void send is safe to iterate
    }
}

function iteratesIntSend(): void
{
    foreach (intSend() as $value) { // E?: foreach does not send, so a non-nullable TSend is unsafe
        echo $value;
    }
}

function sendsOnIntSend(): void
{
    $generator = intSend();
    $generator->send(1); // V
    $other = intSend();
    $other->send('x'); // E: TSend is int, not string
}
