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

## Corpus-sourced discovery

Beyond hand-picked tracker issues, `conformance/corpus/` replays an analyzer's own test
corpus across every tool to surface divergences at scale (Mago `@mago-expect` cases as the
first baseline). Confirmed soundness divergences are promoted into the `regressions_*` tests
above. The string-narrowing case is the first promotion from that track.

## Backlog

Candidate edge cases to extract next. Each should become one focused `regressions_*.php`
file following the method above.

- [ ] **Optional *extra* key + list** — `array{0: int, a?: string}` under `array_is_list()`.
  The list-compatible required key `0` plus an optional non-list key; `[0 => 1]` is a valid
  list value. Same family as above, second reproduction in
  [phpstan#14938](https://github.com/phpstan/phpstan/issues/14938).
- [ ] **Explicit-key sealed shape list-ness** — `array{0: string, 1: string}` vs.
  `array{string, string}`. Per the [survey matrix](https://github.com/phpstan/phpstan/discussions/14939),
  Psalm answers "not a list" for the explicit-key form while PHPStan/Mago answer "list";
  the keyless form agrees. Probe whether each tool treats the two spellings identically.
- [ ] **Reversed literal at a `list{}` parameter** — passing `[1 => 'x', 0 => 'y']`
  (provably not a list at runtime) to a `list{string, string}` parameter. Phan rejects it;
  PHPStan/Psalm/Mago accept it (survey matrix, #14939). Tests soundness of list-shape
  parameter acceptance.
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
