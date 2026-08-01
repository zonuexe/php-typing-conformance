<?php

declare(strict_types=1);

namespace Conformance\LspFixture;

/**
 * The hover-conformance targets: each probe hovers a variable here and asks
 * whether the type the server shows is the type the annotation or the
 * narrowing established, the widened fallback, or nothing. The expected
 * spellings live next to the probes in probes.toml, not in this file.
 */

/** The control case: a native return type every server knows. */
function nativeReturn(): void
{
    $length = strlen('subject');
    $length; // hover expects int, from the native signature alone
}

function narrowedUnion(int|string $value): void
{
    if (is_int($value)) {
        $value; // hover expects int, the narrowed half of the union
    }
}

/** @return array{name: string, age: int} */
function makeShape(): array
{
    return ['name' => 'a', 'age' => 1];
}

function shapedArray(): void
{
    $user = makeShape();
    $user; // hover expects the array shape, not plain array
}

function intRange(): void
{
    /** @var int<1, 100> $percent */
    $percent = 50;
    $percent; // hover expects int<1, 100>, or int as the widened fallback
}

/**
 * @template T
 * @param list<T> $items
 * @return T
 */
function firstItem(array $items): mixed
{
    return $items[0];
}

function genericCall(): void
{
    /** @var list<string> $names */
    $names = ['alpha', 'beta'];
    $firstName = firstItem($names);
    $firstName; // hover expects string, the resolved T
}
