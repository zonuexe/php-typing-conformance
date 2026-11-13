<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmAssertThisProperty;

/**
 * Assertion target `$this->prop`.
 *
 * `@psalm-assert-if-true` (and the PHPStan spelling) can name a property of
 * the receiver, not only a parameter. After `hasException()`, `$this->exception`
 * is `Exception`, so `=== null` is dead.
 *
 * References:
 * - Psalm adding_assertions.md: Asserting return values of methods (`$this->exception`)
 * - PHPStan narrowing-types.md: properties on the same object
 */

final class Result
{
    public ?\Exception $exception = null;

    /**
     * @psalm-assert-if-true \Exception $this->exception
     */
    public function hasException(): bool // T: @psalm-assert-if-true
    {
        return $this->exception !== null;
    }
}

function example(Result $result): void
{
    if ($result->hasException()) {
        echo $result->exception->getMessage(); // V

        echo $result->exception === null ? 'null' : 'ex'; // E?: after assert Exception, === null is always false
    }
}
