<?php

declare(strict_types=1);

namespace Conformance\Tests\DebugPhanDebugVar;

/**
 * Cross-tool handling of `@phan-debug-var`.
 *
 * Phan requires a *string expression statement* (not a comment) so the AST
 * exposes the annotation. Honouring it means a `PhanDebugAnnotation` with the
 * inferred union type. Other tools usually see a no-op string statement and
 * may report "expression has no effect" / "does not do anything" — that is
 * style noise, not feature enforcement, so it is marked `// E?[noise]`.
 *
 * Phan attributes the diagnostic to the enclosing statement line (`if`), not
 * to the string line itself. The enforcement probe therefore sits on the `if`.
 *
 * References:
 * - Phan Annotating-Your-Source-Code-V6.md: `@phan-debug-var`
 *
 * @conformance-kind debug
 */

function example(int|string $value): void // T: @phan-debug-var
{
    if (\is_int($value)) { // E?: PhanDebugAnnotation is attributed here (enclosing statement)
        // Must be a bare string statement — php-ast does not surface comments.
        '@phan-debug-var $value'; // E?[noise]: non-Phan "no effect" / unused-statement noise — not enforcement
    }
}
