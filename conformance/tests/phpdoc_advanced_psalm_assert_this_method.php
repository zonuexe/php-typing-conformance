<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmAssertThisMethod;

/**
 * Assertion target `$this->method()`.
 *
 * Psalm documents that asserting a method return only holds when the class
 * is `@psalm-immutable` (or `memoizeMethodCallResults` is on). This file
 * uses the immutable annotation; the suite config does not enable memoization.
 *
 * References:
 * - Psalm adding_assertions.md: `$this->getException()` + `@psalm-immutable`
 * - PHPStan narrowing-types.md: returned values from other methods
 */

/**
 * @psalm-immutable
 */
final class Result
{
    public function __construct(
        private readonly ?\Exception $exception = null,
    ) {
    }

    /**
     * @psalm-assert-if-true \Exception $this->getException()
     */
    public function hasException(): bool // T: @psalm-assert-if-true
    {
        return $this->exception !== null;
    }

    public function getException(): ?\Exception
    {
        return $this->exception;
    }
}

function example(Result $result): void
{
    if ($result->hasException()) {
        echo $result->getException()->getMessage(); // V

        echo $result->getException() === null ? 'null' : 'ex'; // E?: after assert, getException() is not null
    }
}
