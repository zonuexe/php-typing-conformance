<?php

declare(strict_types=1);

namespace Conformance\Tests\AssertionsPropertyTypeNarrowing;

/**
 * Type checks narrow object properties the way they narrow locals.
 *
 * After `is_string($this->name)`, `$this->name` is `string` for the rest of
 * the branch. A tool that only narrows variables still sees `string|null` on
 * the property and rejects `takesString`, so honour is silence on the guarded
 * call. The unguarded call shows the tool reports `string|null` to `string`
 * at all; without it, silence on the guarded call would prove nothing.
 *
 * Source: PHPantom-dev/phpantom_lsp#466 (0.11.1).
 *
 * References:
 * - https://github.com/PHPantom-dev/phpantom_lsp/pull/466
 */

final class Named
{
    public function __construct(
        private string|null $name,
    ) {
    }

    public function label(): string
    {
        if (!is_string($this->name)) {
            return '';
        }

        takesString($this->name); // Q: the property is string in this branch

        return $this->name;
    }

    public function unguarded(): void
    {
        takesString($this->name); // E: string|null is not string without the guard
    }
}

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}
