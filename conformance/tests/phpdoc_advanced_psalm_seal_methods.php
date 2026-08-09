<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmSealMethods;

/**
 * Cross-tool handling of `@psalm-seal-methods` / `@no-seal-methods`.
 *
 * The positive `@psalm-seal-methods` cannot be observed on its own: Psalm seals
 * magic methods by default (`sealAllMethods="true"`), and PHPStan / Mago / phpy
 * likewise distrust a `__call` method that has no matching `@method` tag. So a
 * call to an undeclared magic method is rejected *with or without* the seal tag,
 * and that rejection proves nothing about whether the tag was read.
 *
 * The lever that actually discriminates is the inverse tag
 * `@psalm-no-seal-methods`: it *lifts* the default seal, so an undeclared magic
 * call becomes allowed. Only a tool that models the seal machinery goes silent
 * there; a tool that ignores the tag keeps its default diagnostic. That silence
 * is the real signal, so it is scored with a quiet probe on the origin tools.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-seal-methods`, `@psalm-no-seal-methods`
 * - Psalm configuration.md: `sealAllMethods` (default true)
 */

/**
 * Sealed (the default). An undeclared magic call is rejected here, but every
 * default-sealing tool does that regardless of the tag, so the diagnostic is
 * baseline noise rather than evidence the seal tag was honoured.
 *
 * @method string greet()
 * @psalm-seal-methods
 */
final class Sealed // T: @psalm-seal-methods
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        return $name === 'greet' ? 'hello' : throw new \BadMethodCallException($name);
    }
}

/**
 * Explicitly unsealed. `@psalm-no-seal-methods` lifts the default seal, so a
 * tool that honours the seal family must now *allow* an undeclared magic call.
 * Silence is the discriminator; a tool that ignores the tag keeps its default
 * "undefined magic method" diagnostic.
 *
 * @method string greet()
 * @psalm-no-seal-methods
 */
final class Unsealed // T: @psalm-no-seal-methods
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed // E?[noise]: some tools flag the docblock/native array mismatch
    {
        return $name === 'greet' ? 'hello' : throw new \BadMethodCallException($name);
    }
}

$sealed = new Sealed();
$sealed->greet();

// Rejected by every default-sealing tool, tag or no tag — baseline, not the
// seal tag under test. A bare statement avoids a confounding mixed-return
// diagnostic from the surrounding expression.
$sealed->wave(); // E?[noise]: undeclared magic method rejected by default sealing

$unsealed = new Unsealed();
$unsealed->greet();

// Honouring the seal family means `@psalm-no-seal-methods` lifts the seal and
// this undeclared call is allowed (silence). A tool that ignores the tag still
// reports it; that report is noise, not tag enforcement. Quiet is origin-only
// (Psalm/pzoom), the only tools confirmed to model the no-seal lever.
$unsealed->wave(); // Q?<psalm> // Q?<pzoom> // E?[noise]
