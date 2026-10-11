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

final class Customer
{
    public function name(): string
    {
        return 'customer';
    }
}

final class Receipt
{
    public function total(): int
    {
        return 1;
    }
}

/** @param list<Customer> $customers */
function inArrayNeedle(?Customer $candidate, array $customers): void
{
    if (in_array($candidate, $customers, true)) {
        $candidate; // hover expects Customer, null ruled out by the strict search
    }
}

/** @param list<Customer> $customers */
function arrayMapCallback(array $customers): void
{
    array_map(function ($mapped) {
        return $mapped; // hover expects Customer, from array_map's callable signature
    }, $customers);
}

/** @template T */
final class Batch
{
    /** @param callable(T): void $callback */
    public function each(callable $callback): void
    {
    }
}

/** @param Batch<Customer> $batch */
function templateCallback(Batch $batch): void
{
    $batch->each(function ($member): void {
        $member; // hover expects Customer, T bound from Batch<Customer>
    });
}

/**
 * @param list<Customer> $left
 * @param list<Customer> $right
 */
function mergedLists(array $left, array $right): void
{
    $merged = array_merge($left, $right);
    $merged; // hover expects list<Customer>, the element type carried through
}

function newFromClassConstant(Customer $customer): void
{
    $class = $customer::class;
    $copy = new $class();
    $copy; // hover expects Customer, from the object's ::class
}

function pipeResult(): void
{
    $piped = 'Hello' |> strlen(...);
    $piped; // hover expects int, the callable's result rather than the callable
}

function untypedFactory()
{
    return new Receipt();
}

function inferredReturn(): void
{
    $inferred = untypedFactory();
    $inferred; // hover expects Receipt, inferred from the body
}

/** @return \Generator<int, Customer, mixed, Receipt> */
function receiptGenerator(): \Generator
{
    yield new Customer();

    return new Receipt();
}

function generatorReturn(): void
{
    $returned = receiptGenerator()->getReturn();
    $returned; // hover expects Receipt, the Generator's TReturn
}

/** @param list<Customer> $customers */
function listKey(array $customers): void
{
    foreach ($customers as $position => $customer) {
        $position; // hover expects int, the key of a list
    }
}
