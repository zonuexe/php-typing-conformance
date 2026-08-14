<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedPsalmSealProperties;

/**
 * Cross-tool handling of `@psalm-seal-properties` / `@no-seal-properties`.
 *
 * The positive `@psalm-seal-properties` cannot be observed on its own: Psalm
 * seals magic properties by default (`sealAllProperties="true"`), and PHPStan /
 * Mago / phpy likewise distrust a `__get`/`__set` pair with no matching
 * `@property` tag. So access to an undeclared magic property is rejected *with or
 * without* the seal tag, and that rejection proves nothing about whether the tag
 * was read.
 *
 * The lever that actually discriminates is the inverse tag
 * `@psalm-no-seal-properties`: it *lifts* the default seal, so an undeclared
 * magic property becomes allowed. Only a tool that models the seal machinery
 * goes silent there; a tool that ignores the tag keeps its default diagnostic.
 * That silence is the real signal, so it is scored with a quiet probe on the
 * origin tools.
 *
 * References:
 * - Psalm supported_annotations.md: `@psalm-seal-properties`, `@psalm-no-seal-properties`
 * - Psalm configuration.md: `sealAllProperties` (default true)
 */

/**
 * Sealed (the default). An undeclared magic property is rejected here, but every
 * default-sealing tool does that regardless of the tag, so the diagnostic is
 * baseline noise rather than evidence the seal tag was honoured.
 *
 * @property string $name
 * @psalm-seal-properties
 */
final class SealedBag // T: @psalm-seal-properties
{
    public function __get(string $name): mixed
    {
        return $name === 'name' ? '' : null;
    }

    public function __set(string $name, mixed $value): void
    {
    }
}

/**
 * Explicitly unsealed. `@psalm-no-seal-properties` lifts the default seal, so a
 * tool that honours the seal family must now *allow* an undeclared magic
 * property. Silence is the discriminator; a tool that ignores the tag keeps its
 * default "undefined magic property" diagnostic.
 *
 * @property string $name
 * @psalm-no-seal-properties
 */
final class UnsealedBag // T: @psalm-no-seal-properties
{
    public function __get(string $name): mixed
    {
        return $name === 'name' ? '' : null;
    }

    public function __set(string $name, mixed $value): void
    {
    }
}

$sealed = new SealedBag();
$sealed->name = 'Ada'; // V

// Rejected by every default-sealing tool, tag or no tag — baseline, not the
// seal tag under test.
$sealed->extra = 1; // E?[noise]: undeclared magic property rejected by default sealing

$unsealed = new UnsealedBag();
$unsealed->name = 'Ada'; // V

// Honouring the seal family means `@psalm-no-seal-properties` lifts the seal and
// this undeclared assignment is allowed (silence). A tool that ignores the tag
// still reports it; that report is noise, not tag enforcement. Quiet is
// origin-only (Psalm/pzoom), the only tools confirmed to model the no-seal lever.
$unsealed->extra = 1; // Q?<psalm> // Q?<pzoom> // E?[noise]
