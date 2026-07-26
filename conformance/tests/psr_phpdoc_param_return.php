<?php

declare(strict_types=1);

namespace Conformance\Tests\PsrPhpdocParamReturn;

/**
 * FIG proposed PHPDoc: `@param` / `@return` as the baseline type contract.
 *
 * The proposed PHPDoc standard treats `@param` and `@return` as the primary
 * documentation of callable types. Static analyzers in the PHP ecosystem
 * implement that contract by rejecting call/return mismatches against those
 * tags even when native typehints are absent.
 *
 * This group anchors that expectation to the FIG drafts rather than to a
 * single analyzer's docs.
 *
 * References:
 * - references/fig-standards/proposed/phpdoc.md
 * - references/fig-standards/proposed/phpdoc-tags.md
 */

/**
 * @param string $message
 * @return int
 */
function encodeMessage($message)
{
    return strlen($message);
}

/**
 * @param int $code
 */
function takesCode($code): void
{
}

takesCode(encodeMessage('hello'));

// @param string rejects int.
// Intelephense type-checks native declarations primarily and often skips
// PHPDoc-only parameters (same gap as phpdoc_basics_param_types).
encodeMessage(42); // E?: int is not accepted by @param string // E<phpstan>: int is not accepted by @param string // E<phpstan-strict>: int is not accepted by @param string // E<psalm>: int is not accepted by @param string // E<mago>: int is not accepted by @param string // E<mir>: int is not accepted by @param string // E<phan>: int is not accepted by @param string // E<noverify>: int is not accepted by @param string

// @return int value is not accepted by @param string.
encodeMessage(encodeMessage('x')); // E?: nested call returns int, not string
