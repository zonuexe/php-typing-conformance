<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysShapeSealedByDefault;

/**
 * Is `array{foo: int}` sealed?
 *
 * A shape written without a trailing `...` declares the keys it knows about
 * and says nothing out loud about the rest, so the default has to be read off
 * behaviour: sealed means an undeclared key is a type error, unsealed means it
 * is admitted the way `array{foo: int, ...}` admits it. PHPStan and Psalm both
 * seal by default — that is what makes `...` worth spelling — and an analyzer
 * that leaves shapes open instead accepts a payload the declaration never
 * promised to handle.
 *
 * The first two calls are controls rather than the question: every analyzer
 * that models shapes at all rejects them, so silence there means shapes are not
 * checked, while silence on the undeclared key alone means the shape is open.
 *
 * References:
 * - PHPStan array shapes
 * - Psalm "Unsealed array and list shapes", where `...` is the opt-in
 */

/**
 * @param array{foo: int} $shape
 */
function takesShape(array $shape): void
{
}

// Controls: the declared key is required, and it is an int.
takesShape([]); // E: array{} does not have the required key foo
takesShape(['foo' => 'one']); // E: foo is int, not string

// The question, asked of a literal.
takesShape(['foo' => 1]);
takesShape(['foo' => 2, 'buz' => 42.0]); // E: a sealed array{foo: int} does not admit the undeclared key buz

/**
 * @param array{foo: int, buz: float} $wider
 */
function passesWiderShapeAlong(array $wider): void
{
    // And of a value whose extra key comes from a declaration rather than from
    // a literal at the call site.
    takesShape($wider); // E: array{foo: int, buz: float} is not a subtype of a sealed array{foo: int}
}
