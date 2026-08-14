# Classification audit (2026-08)

How Pass/Fail and Unrecognized/Enforced/Not enforced are derived, where
silence or a mixed error used to look like success, and what the harness
now does about it.

## What the two vocabularies actually measure

A row without `// T` is Pass/Fail against inline markers. A row with `// T`
is scored on two axes:

- **Recognition** — did the analyzer resolve the spelling? Read off the
  `// T` declaration. Silence (or only style / documented-vs-declared noise)
  means yes.
- **Enforcement** — did it then reject the values the spelling excludes?
  Read off the `// E` lines. For PHPStan this is gated by the level.

Those questions are independent. A tool can parse `number` and still accept
`'1'`. It can also fail to parse `number`, treat it as a class, and reject
both `'1'` *and* `1`. The second case used to light up the violating line
and look like support.

## Findings (corpus + committed results)

Numbers are from the committed results at the time of the audit
(215 tests, 142 `// T` rows, 14 tool columns including psalm-next /
phpstan-strict / pzoom).

### Test method

| Gap | Count | Why it matters |
| --- | --- | --- |
| `// T` rows with only `// E?` (no required `// E`) | 129 / 142 | Intentional: silence is **Not enforced**, not Fail. Correct for type-handling. |
| `// T` rows with no `// E` at all | 4 | Enforcement was never asked. Two are documented as unmeasurable (invocation-timing tags; `@phan-output-reference`). |
| Pass/Fail soundness with only optional `// E` | several | Silence is **Pass**. The tool did nothing and the cell was green. |
| `// E?` on a declaration to mean "may not parse" | `arrays_open_shapes`, the false-guard files | That is recognition. Optional E on the declaration mixes parse failure into Pass/Fail. |
| Honour-is-silence probed with `// E?` | `assertions_param_out` | Honour → silent → Pass; ignore and report → Pass; ignore and stay silent → Pass. Every outcome was Pass. |
| Valid calls unmarked | almost every `// T` file | Rejecting a valid `1` for `number` was only a false-positive tag on an **Enforced** cell. |
| `// E` comment text never matched | all files | Any diagnostic on the line counts. A denylist (`isIncidentalDiagnostic`) filters helper-noise only. |

### Result classification

| Combination | Count | What the matrix said | What it often meant |
| --- | --- | --- | --- |
| recognized + enforced | 773 | Enforced | Usually genuine. |
| recognized + enforced + type-mismatch on valid lines | 64 | **Enforced** + ⚠ FP | Rejects valid values too (mir/`number` as a class; Qodana drops `...` and seals the shape; PHPStan over-strict `pure-closure`). |
| unrecognized + enforced | 121 | Unrecognized | Incidental: unknown type treated as a class, every argument rejected. Already hidden in the matrix; now also recorded as `over_rejected_lines` when the valid call type-rejects. |
| recognized + none | 714 | Not enforced / Widened | Genuine non-enforcement, or a curated fallback. |
| Intelephense `P1131` on a `// T` line | several | Unrecognized | The type *was* parsed; "documented type is not compatible with declared type" is a native/PHPDoc mismatch after recognition. |
| NoVerify `Use bool instead of boolean` | synonym tests | Unrecognized | Style nit after the synonym was understood. |

The worst misread is **recognized + enforced + valid values also rejected**.
mir on `phpdoc_advanced_fallback_number` does not complain on the
declaration (so "recognized"), then rejects `1`, `1.5`, `'1'` and `true`
as not being class `number`. The cell said Enforced.

## What changed

### Harness

- **`// V`** — valid control. The line must stay silent for enforcement to
  be genuine. A type-rejection there is over-rejection, not a random false
  positive. Distinct from `// Q`, which *counts* silence as honouring a
  suppress tag.
- **`over_rejected_lines`** — derived from `// V` lines and from unmarked
  false positives that look like type mismatches. Unused-parameter lint
  does not count.
- **Incidental cell** — recognized + over-rejected renders as
  `Incidental (n/m)` instead of Enforced. Unrecognized still wins the
  matrix word when the spelling itself failed to resolve.
- **Recognition filter** — `P1131`, `Use bool instead of boolean`,
  `PhanTypeMismatchDeclaredReturn`, unused / `missingType.*` on a `// T`
  line are not recognition failures. Unextractable annotations and
  undeclared types still are.
- **`--rescore`** — `php conformance/src/main.php --rescore` (or
  `make rescore`) re-derives classifications from stored `output` without
  re-running analyzers.
- **Self-test** — `make test-harness` covers genuine enforcement,
  over-rejection, unmarked FPs, unused-parameter FPs, `P1131`, undeclared
  types, dumpType noise, and silent T-rows.

### Corpus

- `// V` on valid calls and `@return` bodies in the `phpdoc_advanced_fallback_*`
  family, plus the unsealed-shape tests.
- `arrays_open_shapes` / `arrays_list_shape_destructuring` / the false-guard
  files: declaration `// E?` replaced with `// T`. Silence is now Not
  enforced, not Pass.
- `phpdoc_advanced_pseudotype_class_precedence`: `// T` + `// V` on the
  class-resolved call.
- `assertions_param_out`: `// T: @param-out` and `// Q?` on the honour-is-silence
  line (was `// E?`, which made every outcome Pass).
- `historical_implicit_nullable_parameter`: tagged `@conformance-kind style`
  (advisory language nit, not a type-safety row).

Four `// T` tests remain no-probes on purpose:
`phpdoc_advanced_phpstan_not_deprecated`,
`phpdoc_advanced_phpstan_param_immediately_invoked_callable`,
`phpdoc_advanced_phpstan_param_later_invoked_callable`,
`phpdoc_advanced_vendor_prefixed_output_reference_phan`. Their honour
signal is not measurable with the rules this harness enables.
`@not-deprecated` would need a tool that inherits parent deprecations onto
overrides (none here do); the invocation-timing tags feed checked-exceptions,
which this suite does not enable.

A second pass added `// V` to the remaining type-spelling rows (synonyms,
Phan/Psalm dialect types, `new<T>`, vendor-prefixed param/return/var/import,
never-return). Debug helpers and Q-primary tag tests were left alone:
dump/inspect lines *are* the probe, and honour-is-silence is already `// Q`.

A third pass added `// V` to tag rows that have a clear valid side
(`GoodChild`, `AllowedOne`, constructor-compatible children, declared
magic `greet()` / `$name`, assert-then-`takesInt`, inside-namespace
`@psalm-internal`) and turned `aliases_local_type` /
`aliases_import_type` into `// T` rows so alias-as-class rejection of
the complete shape is incidental rather than a tool-specific Pass.

## What we still do not do

- **Match `// E: comment` text against the diagnostic.** Tool messages do
  not share a vocabulary. The denylist (helper-noise, no-op expressions)
  plus `// V` is the substitute.
- **Mark `// V` on every non-fallback `// T` file.** Unmarked valid calls
  still feed `over_rejected_lines` when the diagnostic is a type mismatch,
  so the Incidental cell works without the marker. Prefer `// V` on new
  tests.
- **Treat optional-only Pass/Fail silence as Fail.** Remaining optional-only
  soundness rows are genuine "tools may or may not report this" cases
  (`objects_static_return_mismatch` uses a required group; discriminant
  match is still optional because narrowing is tool-divergent). Prefer
  `// T` whenever the question is a spelling.

## Maintainer commands

```sh
make test-harness
make rescore                          # re-derive from stored output
php conformance/src/audit-classification.php
php conformance/src/apply-valid-controls.php [--apply] [prefix]
```
