<?php

declare(strict_types=1);

namespace Conformance\Tests\RegressionsUnsetPseudoType;

/**
 * `unset` as a possibly-undefined pseudo-type.
 *
 * In a top-level script, a `@var` union carrying `unset` — the Blade-view
 * idiom — states that the variable is either the given type or not defined
 * at all. Reading `unset` as the undefined state, or as a nullable reading
 * of the union, are both honest interpretations; resolving it as a class
 * name is the defect. PHPantom reports "Class 'unset' not found" for it
 * (issue #366), and most tools measured here resolve the spelling the same
 * way — as an unknown class rather than as the possibly-undefined state.
 *
 * Each probe gets its own variable so that one read cannot narrow the next.
 * A method call and a function argument are both unsafe on the undefined
 * path, so a tool that models the state flags them. Inside an `isset()`
 * guard the undefined path is gone: the guarded reads must stay silent, and
 * the guard itself must not be reported as redundant — a variable that may
 * be undefined makes `isset()` meaningful.
 *
 * The `// V` controls repeat both reads under a plain `\DateTime` `@var`. A
 * diagnostic there means the reactions below are about a top-level variable
 * with no assignment, not about the `unset` member of the union.
 *
 * mir looked like the exception and is not one. Its source resolves `unset`
 * the same way as the rest: the spelling is absent from the docblock keyword
 * table, falls through to the catch-all named-object atom, and is flagged
 * because it is not in the `is_pseudo_type` exemption list. That diagnostic
 * is info severity, which this adapter used to ask for only on `debug_*`
 * files — mir's apparent silence here was the harness's, and MirChecker now
 * reads info everywhere. mir does carry a possibly-undefined concept, but it
 * is set from control flow alone and no docblock type can reach it, so
 * exempting the spelling would quiet the complaint without implementing any
 * of the semantics above.
 *
 * Source lead: PHPantom-dev/phpantom_lsp#366. Tracked here in #7.
 */

/** @var \DateTime $defined */
echo $defined->format('Y-m-d'); // V: the same method call without `unset` in the union
echo date_format($defined, 'Y-m-d'); // V: the same argument without `unset` in the union

/** @var \DateTime|unset $read */
echo $read->format('Y-m-d'); // E?: $read may be undefined (`unset`) or not a DateTime // T: unset

/** @var \DateTime|unset $passed */
echo date_format($passed, 'Y-m-d'); // E?: same through a function argument // T: unset

/** @var \DateTime|unset $guarded */
if (isset($guarded)) { // Q: `unset` in the union makes the guard meaningful, not redundant // T: unset
    echo $guarded->format('Y-m-d'); // Q: isset() must narrow the undefined state away
    echo date_format($guarded, 'Y-m-d'); // Q: same through a function argument
}
