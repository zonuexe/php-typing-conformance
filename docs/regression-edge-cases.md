# Cross-Analyzer Regression Edge Cases

## Purpose

The feature-based test groups describe *what the type system should do*, organized by
language and PHPDoc topic. This document tracks a second, orthogonal axis: **edge cases
distilled from the issue trackers of individual analyzers.**

These live in the `regressions` test group (`conformance/tests/regressions_*.php`,
`source_category = "regression"`).

## Hypothesis

> An edge case that surfaced as a bug in one analyzer is, empirically, a latent problem in
> the others.

Analyzers implement overlapping but independently written models of the same type
semantics. When one project files and fixes a soundness or precision bug, the same input
is worth replaying against every tool: often at least one other analyzer shares the wrong
verdict, and the corpus captures who diverges before and after upstream fixes land.

The motivating survey that seeded this axis compared tool behavior directly across
PHPStan, Psalm, Mago, and Phan:
<https://github.com/phpstan/phpstan/discussions/14939>.

## Method

1. Start from a concrete tracker issue (bug report, fix PR, or regression test) in one
   analyzer.
2. Reduce it to the smallest PHP file that still poses the same type-system question.
3. Run it against **all** tools via the harness (`php conformance/src/main.php`, or the
   normal add-a-test flow) and read each tool's actual diagnostics.
4. Encode the *current* per-tool verdict with tool-scoped markers
   (`// E<phpstan>`, `// E<psalm>`, …). A tool with no marker is asserted to stay silent.
5. Cite every source issue in the file's header docblock.

Because markers record current reality per tool, `conformance_automated = "Pass"` means
"we captured this tool's behavior accurately," **not** "this tool is correct." When an
analyzer later changes its verdict (e.g. a fix ships), its column flips to `Fail`,
flagging that the expectation and this catalog should be updated. That flip is the signal
the suite exists to produce.

## Status

### Optional-key array shapes vs. `array_is_list()`

Test: [`regressions_optional_key_shape_is_list.php`](../conformance/tests/regressions_optional_key_shape_is_list.php)

A shape whose only keys are optional (`array{a?: string}`) admits the empty array `[]`, and
`array_is_list([]) === true`. So `array_is_list($a)` is *maybe true*, not "always false",
and narrowing to a list is reachable. Multiple analyzers instead treated the optional key
as required and collapsed the check to impossible/always-false.

| Tool | Verdict on this edge case | Source | Status |
|------|---------------------------|--------|--------|
| PHPStan | ✗ always-false (`function.impossibleType`) | [phpstan#14938](https://github.com/phpstan/phpstan/issues/14938) | fix in [phpstan-src#6025](https://github.com/phpstan/phpstan-src/pull/6025) |
| Psalm | ✗ `TypeDoesNotContainType` | [psalm#11905](https://github.com/vimeo/psalm/issues/11905) | open |
| Mago | ✗ `impossible-type-comparison` (1.19) | [mago#2073](https://github.com/carthage-software/mago/issues/2073) | fixed in 1.43 |
| Phan | ✓ maybe (no diagnostic) | — | runtime-correct |
| NoVerify | n/a — does not parse shape syntax | — | out of scope |

When PHPStan 2.x picks up phpstan-src#6025, and when Mago is upgraded past 1.43, their
columns will flip and the markers need refreshing.

### `@assert-if-true` narrowing to a string subtype

Test: [`regressions_string_narrowing_assert_if_true.php`](../conformance/tests/regressions_string_narrowing_assert_if_true.php)

A user-defined predicate carrying a non-vendor-prefixed `@assert-if-true non-empty-string $s`
should narrow its argument in the guarded branch, so a following call requiring
`non-empty-string` type-checks. Tools diverge on whether they honor the bare annotation.

| Tool | Verdict | Diagnostic |
|------|---------|-----------|
| PHPStan / PHPStan-strict | ✗ does not narrow | `argument.type` |
| Psalm | ✗ does not narrow | `ArgumentTypeCoercion` |
| Mago | ✓ narrows, accepts | — |
| Phan | ✓ narrows, accepts | — |
| NoVerify | n/a — cannot evaluate `non-empty-string` | flags the signature |

Discovered by the corpus sweep (see `conformance/corpus/`) from Mago's
`reconcile_non_empty_string` case; distilled here into a minimal, self-authored repro.

### Array element narrowing by null subtraction

Test: [`regressions_array_element_null_subtraction.php`](../conformance/tests/regressions_array_element_null_subtraction.php)

`$arr = [$val]` (with `$val: string|null`) has type `list{string|null}`. After
`if ($arr === [null]) { return; }`, the else path has excluded the only null-element value,
so `$arr` narrows to `list{string}`. Tools diverge on whether they subtract the null case
from the array shape through the equality check.

| Tool | Verdict | Diagnostic |
|------|---------|-----------|
| PHPStan / PHPStan-strict | ✗ does not subtract | `argument.type` |
| Psalm | ✗ does not subtract | `ArgumentTypeCoercion` |
| Mago | ✓ narrows to `list{string}` | — |
| Phan | ✓ narrows | — |
| NoVerify | n/a — cannot evaluate `list{string}` | flags the signature |

Discovered by the corpus sweep from Mago's `array_reconcile` case
(`test_null_string_subtraction`); distilled into a minimal, self-authored repro. Same
PHPStan+Psalm vs. Mago+Phan split as the `@assert-if-true` case above.

### Positional destructuring of a string-keyed array

Test: [`regressions_list_destructure_string_key.php`](../conformance/tests/regressions_list_destructure_string_key.php)

`[$a, $b] = $stringKeyed` reads positional integer keys 0 and 1 from an
`array<string, int>`, which has no integer keys — the destructured variables are always
undefined at runtime. This is the **inverse** direction from the narrowing cases: here the
tools that were lenient are strict, and one lenient tool misses the defect.

| Tool | Verdict | Diagnostic |
|------|---------|-----------|
| PHPStan / PHPStan-strict | ✓ detects | `offsetAccess.notFound` (+ `echo.nonString` downstream) |
| Mago | ✓ detects | `mismatched-array-index` |
| Phan | ✓ detects | `PhanTypeMismatchArrayDestructuringKey` |
| Psalm | ✗ misses | — |
| NoVerify | ✗ misses | — |

Discovered by the corpus sweep from Mago's `list_destructure_string_key_simple` case.
Notably the split differs from the narrowing cases: **Psalm** is the tool that misses here.

## Corpus-sourced discovery

Beyond hand-picked tracker issues, `conformance/corpus/` replays an analyzer's own test
corpus across every tool to surface divergences at scale (Mago `@mago-expect` cases as the
first baseline). Confirmed soundness divergences are promoted into the `regressions_*` tests
above. The string-narrowing case is the first promotion from that track.

## Backlog

Candidate edge cases to extract next. Each should become one focused `regressions_*.php`
file following the method above.

- [x] **Optional *extra* key + list** — `array{0: int, a?: string}` under `array_is_list()`.
  Done: [`regressions_optional_extra_key_is_list.php`](../conformance/tests/regressions_optional_extra_key_is_list.php).
  Same verdict as the base case (PHPStan/Psalm/Mago wrong, Phan correct);
  second reproduction from [phpstan#14938](https://github.com/phpstan/phpstan/issues/14938).
- [x] **Explicit-key sealed shape list-ness** — `array{0: string, 1: string}` vs.
  `array{string, string}`. Done:
  [`regressions_explicit_key_shape_is_list.php`](../conformance/tests/regressions_explicit_key_shape_is_list.php).
  Confirmed: Psalm alone rejects the explicit-key spelling as a `list<string>`
  (`ArgumentTypeCoercion`) while accepting the keyless spelling; PHPStan/Mago/Phan accept
  both. Survey matrix [#14939](https://github.com/phpstan/phpstan/discussions/14939).
- [x] **Reversed literal at a `list{}` parameter** — passing `[1 => 'x', 0 => 'y']`
  (provably not a list at runtime) to a `list{string, string}` parameter. Done:
  [`regressions_reversed_literal_list_param.php`](../conformance/tests/regressions_reversed_literal_list_param.php).
  Result refines the survey: **both Phan and Psalm** reject it (`PhanTypeMismatchArgument`
  / `ArgumentTypeCoercion`); PHPStan and Mago accept it. (The survey #14939 listed only
  Phan as rejecting — Psalm's version here also flags it.)
- [ ] **`array_is_list()` on the keyless tuple shape** — `array{int, string}` narrowing,
  to confirm the "list?" verdict is consistent with the explicit-key case above.

### Sourcing leads

When mining for new entries, scan recent fix commits and closed issues labeled around
type narrowing, shape/list reconciliation, and template variance in:

- Mago — `references/mago` history (e.g. `git log crates/analyzer/tests/cases`).
- PHPStan — <https://github.com/phpstan/phpstan/issues> and phpstan-src regression tests.
- Psalm — <https://github.com/vimeo/psalm/issues> and `tests/` fixtures.

Prefer cases that expose a clean cross-tool divergence over cases that only exercise one
analyzer's extension.
