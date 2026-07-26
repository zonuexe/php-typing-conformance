<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedFamilyBoolLiteral;

/**
 * `true` / `false` literal types
 *
 * As standalone PHPDoc types, `true` and `false` are the two constant boolean
 * types. Analyzers that model them reject the opposite literal; others fall
 * back to plain `bool` and accept it.
 *
 * References:
 * - PHPStan TypeNodeResolver true / false constant boolean types
 */

/** @param true $value */
function acceptsTrue($value): void // T: true
{
}

/** @param false $value */
function acceptsFalse($value): void // T: false
{
}

acceptsTrue(true);
acceptsTrue(false); // E?: false is not the literal type true

acceptsFalse(false);
acceptsFalse(true); // E?: true is not the literal type false
