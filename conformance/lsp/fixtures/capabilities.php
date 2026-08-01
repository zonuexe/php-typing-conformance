<?php

declare(strict_types=1);

namespace Conformance\LspFixture;

/**
 * The all-purpose target for capability smoke probes: every position the
 * probes in probes.toml anchor to lives here (or in lib.php for the
 * cross-file jumps). The one deliberate type error at the bottom exists so a
 * server that publishes diagnostics has something true to say about this
 * file; everything else is ordinary, correct code.
 */
final class ProbeSubject
{
    private Greeter $greeter;

    public function __construct(Greeter $greeter)
    {
        $this->greeter = $greeter;
    }

    public function welcome(string $visitor): string
    {
        $message = $this->greeter->greet($visitor);

        return strtoupper($message);
    }
}

function measureLength(string $subject): int
{
    return strlen($subject);
}

function useEverything(PoliteGreeter $greeter): int
{
    $subject = new ProbeSubject($greeter);
    $renameTarget = $subject->welcome('world');

    return measureLength($renameTarget);
}

/** The deliberate type error: a string returned where int is declared. */
function brokenReturn(): int
{
    return 'not an int';
}
