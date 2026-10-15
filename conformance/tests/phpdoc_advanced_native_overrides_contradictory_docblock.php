<?php

declare(strict_types=1);

namespace Conformance\Tests\PhpdocAdvancedNativeOverridesContradictoryDocblock;

/**
 * Native type wins when PHPDoc names a different kind.
 *
 * `@return bool` on a function whose native return is `Query` is not a
 * narrowing. Runtime still returns a `Query`. A sound checker keeps the
 * native class for call sites: `load()->execute()` is valid, and
 * `takesBool(load())` is not. A checker that substitutes the PHPDoc type
 * rejects the method call and accepts the bool parameter.
 *
 * The nullable-doc-vs-native gap (`@param string` on `?string $name`) is
 * `phpdoc_advanced_param_typehint_nullable_mismatch` and is style. This
 * file is the contradictory-kind case.
 *
 * References:
 * - https://github.com/carthage-software/mago/pull/2494
 */

final class Query
{
    public function execute(): string
    {
        return 'ok';
    }
}

/**
 * @return bool // E?[doc_mismatch+]: PHPDoc bool contradicts native Query
 */
function load(): Query // E?[doc_mismatch+]: PHPDoc bool contradicts native Query
{
    return new Query(); // E?[doc_mismatch+]: body is Query; PHPDoc says bool
}

function takesBool(bool $value): void
{
}

function takesQuery(Query $value): void
{
}

function callExecute(): void
{
    echo load()->execute(); // V: native Query has execute()
}

function passAsQuery(): void
{
    takesQuery(load()); // V: native return is Query
}

function passAsBool(): void
{
    takesBool(load()); // E: Query is not bool
}
