<?php

declare(strict_types=1);

namespace Conformance\Tests\ArraysUnsealedShapeOptionalKey;

/**
 * `array{foo: int, bar?: bool, ...}`
 *
 * An optional key and a trailing `...` in one shape. Both say something about
 * keys that may be absent, and an analyzer that collapses them reads `bar?` as
 * just another extra key — which is silent until `bar` shows up with a value
 * the declaration forbids.
 *
 * References:
 * - PHPStan array shapes, `array{'foo': int, "bar"?: string}` for the optional key
 * - Psalm "Unsealed array and list shapes" for the trailing `...`
 */

/**
 * @param array{foo: int, bar?: bool, ...} $shape
 */
function acceptsUnsealedShapeWithOptionalKey(array $shape): void // T: array{foo: int, bar?: bool, ...}
{
}

// The optional key may be left out,
acceptsUnsealedShapeWithOptionalKey(['foo' => 1]); // V
acceptsUnsealedShapeWithOptionalKey(['foo' => 2, 'buz' => 42.0]); // V

// supplied,
acceptsUnsealedShapeWithOptionalKey(['foo' => 1, 'bar' => true]); // V
acceptsUnsealedShapeWithOptionalKey(['foo' => 1, 'bar' => false, 'buz' => 42.0]); // V

// or supplied out of declaration order.
acceptsUnsealedShapeWithOptionalKey(['bar' => true, 'foo' => 1]); // V

// Optional is not the same as unconstrained: when `bar` is there it is a bool,
// and `...` does not take over the key the shape already spoke for.
acceptsUnsealedShapeWithOptionalKey(['foo' => 1, 'bar' => 'yes']); // E?: bar is bool, not string

// `foo` carries no `?`, so it stays required. An analyzer that widened the
// whole spelling to plain `array` says nothing on either of these.
acceptsUnsealedShapeWithOptionalKey(['bar' => true]); // E?: array{bar: true} does not have the required key foo
acceptsUnsealedShapeWithOptionalKey(['buz' => 42.0]); // E?: only undeclared keys, so foo is still missing
