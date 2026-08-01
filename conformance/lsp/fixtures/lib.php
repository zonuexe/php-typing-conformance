<?php

declare(strict_types=1);

namespace Conformance\LspFixture;

/**
 * The cross-file half of the probe workspace: capabilities.php resolves this
 * interface and class, so "go to definition" and "go to implementation" have
 * somewhere in another file to land.
 */
interface Greeter
{
    public function greet(string $name): string;
}

final class PoliteGreeter implements Greeter
{
    public function greet(string $name): string
    {
        return 'Hello, ' . $name;
    }
}
