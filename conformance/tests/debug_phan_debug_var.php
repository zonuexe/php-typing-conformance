<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhanDebugVar;

/**
 * Cross-tool handling of `@phan-debug-var`.
 *
 * Phan requires a *string expression statement* (not a comment) so the AST
 * exposes the annotation. Honouring it means a `PhanDebugAnnotation` with the
 * inferred union type. Other tools usually see a no-op string statement.
 *
 * Phan attributes the diagnostic to the enclosing statement line (`if`), not
 * always to the string line itself.
 *
 * References:
 * - Phan Annotating-Your-Source-Code-V6.md: `@phan-debug-var`
 *
 * @conformance-kind debug
 */

function example(int|string $value): void // T: @phan-debug-var
{
    if (\is_int($value)) { // E?: Phan may attribute PhanDebugAnnotation to this line
        // Must be a bare string statement — php-ast does not surface comments.
        '@phan-debug-var $value'; // E?: other tools may blame the string statement (unused expr / debug)
    }
}
