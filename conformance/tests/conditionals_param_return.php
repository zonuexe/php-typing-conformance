<?php

declare(strict_types=1);

namespace Conformance\Tests\ConditionalsParamReturn;

/**
 * Conditional return type driven by a parameter type check.
 *
 * When analyzers model parameter-conditional returns, a string argument
 * yields string and an int argument yields int.
 *
 * References:
 * - PHPStan phpdoc-types: Conditional return types // E<noverify>: NoVerify mis-parses `$value` in the conditional return and attributes the diagnostic here
 * - Psalm type_syntax/conditional_types.md
 * - Intelephense Type-System.md: Conditional Return Type
 */

/**
 * @param string|int $value
 * @return ($value is string ? string : int) // E<phan>: Phan cannot extract conditional return annotations
 */
function identify(string|int $value): string|int
{
    return $value;
}

function takesString(string $value): void
{
}

// Known string argument → return should be string.
takesString(identify('ok'));

// Known int argument → return should be int, not string.
takesString(identify(1)); // E?: int branch of conditional return should not satisfy string
