<?php

declare(strict_types=1);

namespace Conformance\Tests\CallablesPipeOperatorResult;

/**
 * Result type of the pipe operator.
 *
 * PHP 8.5's pipe operator `$value |> $callable` evaluates to the callable's
 * return value, not to the callable. With a first-class callable on the right
 * the result is the function's return type.
 *
 * References:
 * - https://wiki.php.net/rfc/pipe-operator-v3
 */

final class Slug
{
    public function __construct(private string $text)
    {
    }

    public function value(): string
    {
        return $this->text;
    }
}

function slugify(string $text): Slug
{
    return new Slug($text);
}

function pipes(): void
{
    $slug = 'Hello World' |> slugify(...);
    echo $slug->value(); // V: the pipe yields a Slug
    $piped = 'Hello World' |> slugify(...);
    echo $piped->missing(); // E: Slug has no missing()
}

function pipesThroughArrow(): void
{
    $length = 'Hello' |> (fn (string $text): int => strlen($text));
    echo $length + 1; // V: the pipe yields an int
}
