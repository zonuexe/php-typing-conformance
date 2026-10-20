<?php

declare(strict_types=1);

namespace Conformance\Tests\CallablesArrayElementSignature;

/**
 * Callable signatures stored as array values keep their parameter and return
 * types when invoked through the array.
 *
 * Source: vimeo/psalm#11239 (6.3.0).
 *
 * References:
 * - https://github.com/vimeo/psalm/pull/11239
 * - callables_docblock_signature for the non-array form
 */

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

/**
 * @param array<int, callable(string): string> $closures
 */
function invokeStringCallback(array $closures): void // T: callable(string): string
{
    takesString($closures[0]('hello')); // V
    takesInt($closures[0]('hello')); // E: the callable returns string
    $closures[0](1); // E?: the callable parameter is string, not int
}

/**
 * @param array<string, callable(int): int> $closures
 */
function invokeIntCallback(array $closures): void // T: callable(int): int
{
    takesInt($closures['key'](42)); // V
    takesString($closures['key'](42)); // E: the callable returns int
}
