<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmYield;

/**
 * Cross-tool handling of `@psalm-yield`.
 *
 * On a generic type, the tag says that `yield $instance` produces `TValue`
 * (the value sent back into the generator), not the instance itself. Psalm
 * documents that only generic forms are honoured (`@psalm-yield string` is
 * ignored). `example()` is an untyped `Generator`, matching the documented
 * example, so a pinned `TSend=void` cannot masquerade as enforcement.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-yield`
 */

/**
 * @template TValue
 * @psalm-yield TValue
 */
interface Promise // T: @psalm-yield
{
}

/**
 * @template TValue
 * @implements Promise<TValue>
 */
final class Success implements Promise
{
    /**
     * @param TValue $value // E?[noise]
     */
    public function __construct($value) // E?[noise]
    {
    }
}

/**
 * @return Promise<string>
 */
function fetch(): Promise
{
    return new Success('{"data":[]}');
}

function takesString(string $value): void
{
}

function takesInt(int $value): void
{
}

function example(): \Generator // E?[noise]
{
    $data = yield fetch(); // E?[noise]

    // Honouring @psalm-yield TValue on Promise<string> types $data as string.
    // Tools that take Generator's default TSend (void/mixed) reject this.
    takesString($data); // V

    // After @psalm-yield string, $data is not int. Ignoring the tag leaves
    // mixed / Promise, where is_int() is a live check.
    echo \is_int($data) ? 'int' : 'string'; // E?: @psalm-yield string makes is_int() always false
    takesInt($data); // E?: @psalm-yield string is not int
}
