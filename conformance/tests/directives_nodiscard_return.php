<?php

declare(strict_types=1);

namespace Conformance\Tests\DirectivesNodiscardReturn;

/**
 * PHP 8.5 `#[\NoDiscard]`: discarding the return value is a warning, and
 * `(void)` is the documented opt-out at one call site.
 *
 * A discarded call of an otherwise identical function *without* the attribute
 * is the control: a diagnostic there means the reaction is generic unused-return
 * lint, not the attribute. Assignment, `echo`, and `(void)` must stay silent.
 *
 * PHP does not inherit the attribute onto an implementation; this file only
 * probes a concrete function that carries it itself.
 *
 * Source: vimeo/psalm#11892 (6.18.0). PHPStan already had the rule; 2.2.15
 * dropped its PHP-version gate. Phan documents `PhanNoDiscardReturnValueIgnored`.
 *
 * References:
 * - https://wiki.php.net/rfc/marking_return_value_as_important
 * - https://github.com/vimeo/psalm/pull/11892
 */

#[\NoDiscard] // E?[noise]: unknown-attribute class on tools without PHP 8.5 stubs
function mintId(): int // E?[noise]: same, blamed on the signature
{
    echo 'side-effect';

    return 1;
}

function ordinaryId(): int
{
    echo 'side-effect';

    return 1;
}

mintId(); // E: discarded #[\NoDiscard] return is a PHP 8.5 warning
echo mintId(); // V: using the return is the point of the attribute
(void) mintId(); // Q: (void) is the documented per-call opt-out
ordinaryId(); // V: without the attribute, discarding an impure return is fine
