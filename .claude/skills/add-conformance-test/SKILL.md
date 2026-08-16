---
name: add-conformance-test
description: Add a conformance test for a PHP type spelling or PHPDoc feature to php-typing-conformance, or audit one that already exists — pick its group, shape the probes so the measurement means what it claims, re-measure every column, and file the cross-analyzer issue when the finding warrants one. Use whenever a spelling or tag should be measured ("add a test for `unset`", "does any analyzer support X?", "型のテストを追加して", "conformance テストを書いて"), whenever a drafted test is handed over to review or improve ("このテストの測り方は正しい?", "テスト項目を直して"), and whenever an analyzer bug report raises whether the same defect is latent in the other tools. Reach for it even when the ask sounds like just adding a PHP file — a test here is a measuring instrument, and a badly shaped probe records the wrong thing across all fifteen columns without looking wrong. Not for bumping analyzer versions (use update-analyzers), wiring a new analyzer column, or the LSP probes under conformance/lsp.
metadata:
  internal: true
---

# Adding a conformance test

A test in `conformance/tests/` is not a test in the usual sense — nothing
passes or fails a build. It is an instrument that asks fifteen analyzers the
same question and records what each one answered. So the failure mode is not a
broken test, it is a test that measures something other than what its name
says, and reports it with the same confidence as a good one.

Most of this skill is about that failure mode. The mechanics of adding a file
are five minutes; getting the probe shape right is the work.

## 1. Place it in a group

The filename prefix selects the group in `conformance/src/test-groups.toml`.
The two that come up are:

- **`regressions_`** (`source_category = "regression"`) — edge cases distilled
  from analyzer issue trackers. The group's own premise is that a defect found
  in one analyzer is latent in the others, so provenance is the criterion: if
  it came from a bug report, it belongs here.
- **`phpdoc_advanced_`** (`source_category = "ecosystem"`) — utility and
  fallback type spellings. This shelf catalogues spellings **at least one
  vendor implements and documents**, and measures what the others fall back to.

The distinction that actually decides it is whether the spelling has vendor
status. `int-range`, `key-of`, `class-string` do; a spelling nobody implements
does not, and filing it under `ecosystem` asserts a standing it has not got.
When a vendor later implements it, moving the file is the right response.

"PHPDoc tags" is not a group. It is a render-time split of `phpdoc_advanced`
in `SummaryReport::isPhpdocTagCase()`, selected by a `// T: @…` marker or a
`vendor_prefixed` filename. Do not reach for it by renaming a file — that only
works by changing what the `// T` marker claims is under test, which is a lie
about the measurement.

## 2. Shape the probes

`conformance/tests/README.md` has the marker vocabulary (`// E`, `// E?`,
`// Q`, `// V`, `// T`, tags, `[noise]`). Read it. What follows is what the
vocabulary does not tell you — the ways a probe silently measures the wrong
thing.

### One variable per probe

A read that succeeds narrows the variable for everything below it. Reuse one
variable across probes and the later ones stop testing what you think:

```php
/** @var \DateTime|unset $datetime */
echo $datetime->format('Y-m-d');   // probe 1
if (isset($datetime)) {            // probe 2 — no longer measurable
```

If execution reached the `isset()`, the method call above it succeeded, so the
variable *is* defined and the guard *is* redundant. Qodana said exactly that,
in as many words, and it was right. The probe was measuring the earlier read.
Give every probe its own variable and the interference is gone.

### Always add a `// V` control

A probe that fires tells you the tool rejected *something*. Only a control
tells you it rejected the thing under test. So repeat the same operations with
the feature removed — the plain type where the exotic one was — and mark those
lines `// V`.

This is not a formality. On the `unset` test, Phan and Phpactor both flagged
the control exactly as they flagged the probes: their diagnostic was about a
top-level variable with no assignment, and said nothing about the spelling.
Without the control Phan recorded Pass with full enforcement on a feature it
does not implement.

### `// T` must cover every docblock a tool might blame

The `// T` marker covers the line it is on *and the docblock directly above
it* — that one only. This matters because tools split on where they blame an
unresolvable type: PHPStan and Psalm point at the statement, PHPantom, Mago,
php.py and Qodana at the `@var` line. A docblock with no `// T` beneath it
collects those diagnostics as unexpected errors and fails the test for the
wrong reason.

So a test with three annotated declarations needs three `// T` markers. The
marker must be last on its line, and may share a line with `// E` or `// Q`:

```php
/** @var \DateTime|unset $guarded */
if (isset($guarded)) { // Q: the guard is meaningful, not redundant // T: unset
```

### Choose honestly between `// Q` and `[noise]`

`[noise]` says "this diagnostic is incidental and tells us nothing". Before
using it, check whether the diagnostic is actually the tool confessing how it
read the feature. "Variable `$guarded` in isset() always exists and is not
nullable" is not noise — it is PHPStan reporting that it read `T|unset` as a
union of two class names, which is the defect. That is a failed quiet probe,
and marking it noise would have hidden the clearest evidence in the file.

### Keep incidental lint out of the way

`$formatted = $x->format(...);` with `$formatted` never read earns an
UnusedVariable from mir on a probe line. `echo` instead. Small thing, but every
line of incidental output is a line someone has to rule out later.

### Auditing a test that already exists

Often the test is already written and already measured, and the job is to
decide whether it measures what it claims. Read the stored results *first*,
before the test file. A bent probe rarely looks wrong in the source; it shows
up as a reaction that does not make sense, and the analyzers will usually tell
you where to look.

Three signals earn a closer read:

- **A tool that explains its reasoning.** Qodana did not just call the guard
  redundant, it said *why*: "because `$datetime->format('Y-m-d')` is evaluated
  at this point". A diagnostic that cites a line other than its own is naming a
  dependency the probe was not supposed to have.
- **A column passing a feature nobody implements.** If the finding is "nothing
  models this", then Pass with full enforcement is an extraordinary claim.
  Phan's was wrong, and the control is what proved it. Suspicion here is
  cheaper than a wrong row in the matrix.
- **Diagnostics on docblock lines recorded as unexpected errors.** That is
  almost always a missing `// T`, not a misbehaving tool.

Check marker coverage by asking the parser rather than by reading:

```sh
php .claude/skills/add-conformance-test/scripts/dump-markers.php \
  conformance/tests/<name>.php
```

It prints every probe with the kind the parser assigned it, and lists tagged
docblock lines no `// T` reaches. Some of those are harmless — the file
docblock, a declaration no tool will blame — so read them rather than chasing
the count to zero.

Audit and fix in one pass: each edit invalidates the stored results (next
section), so re-measuring between changes buys nothing.

## 3. Measure

```sh
php conformance/src/main.php                 # every column, ~3 minutes
php conformance/src/main.php --tool psalm    # one column, results only
```

Every analyzer now runs once over the whole corpus and slices the result by
file, so a full run is cheap; there is no reason to measure a subset to save
time.

Two things to know:

- **Editing a test file invalidates every stored result for it.** Line numbers
  move, and the results are keyed by line. `--rescore` cannot repair this — it
  re-evaluates stored output, whose line numbers are already wrong. Re-run.
- **Qodana is hand-run** and its report can lie about freshness. Before
  importing, check the line numbers it reports for your file against the
  current source; a new `startTimeUtc` does not mean the IDE re-read the file.
  The `QodanaChecker` and `QodanaSarifReport` docblocks explain why.

## 4. Read the verdicts

Each result TOML carries two independent readings, and conflating them is easy:

- `recognition` — `unrecognized` means the analyzer did not resolve the
  spelling at all (it complained about the type itself, not about a value).
- `enforcement` / `enforced_lines` — how many probes reacted.

Then read `errors_diff` for the controls. **A diagnostic on a `// V` line
invalidates the column's enforcement number**, whatever that number says. The
evaluator only partly catches this: `over_rejected_lines` is populated from
`hasTypeRejection()`, which matches type-mismatch phrasing, so a control
rejected with "Global variable `$x` is undeclared" fails the test and appears
in `errors_diff` while `enforced_lines` still reads `5/5`. Trust `errors_diff`.

When summarising, group the columns by mechanism rather than by verdict —
"resolves the spelling as a class name", "keeps the spelling but rejects the
union", "reacting to the variable, not the spelling", "silent". Pass/Fail
counts hide the finding; the mechanisms are the finding.

## 5. File an issue when the finding warrants one

Not every test earns an issue. File one when the measurement found something
worth collecting outside input on:

- no tool models the feature, and it is unclear whether prior art exists;
- several tools share one defect, so it is an ecosystem question rather than a
  single vendor's bug;
- the semantics themselves are up for discussion.

Do not file one for a test that confirms documented behaviour, or that
reproduces a single tool's bug already tracked upstream — link the upstream
issue from the test's docblock instead.

`references/issue-template.md` holds the structure with a worked example
(issue #7, the `unset` pseudo-type). The short version: **Summary** (what the
spelling means, that nothing measured models it, a link to the upstream
report), **Example** in the idiom's natural form rather than the test file's
expanded one, **Expected semantics** as numbered testable claims — including
the weaker reading you would also accept, so the issue is not demanding one
particular implementation — **Current state** grouped by mechanism with real
quoted diagnostics, and **Open questions**.

```sh
gh issue create --repo zonuexe/php-typing-conformance \
  --title 'The `unset` pseudo-type' --body-file <path>
```

**Do not hard-wrap the body.** GitHub renders a single newline inside a
paragraph as a `<br>`, so text wrapped at 80 or 100 columns arrives with a
forced break at every wrap point and reads as ragged verse. One paragraph is
one line, however long; the same goes for each list item and for PR
descriptions and comments. Only fenced code blocks keep their own line breaks.
This is the opposite of the convention everywhere else in this repository —
commit messages, source, Markdown under `docs/` — so it is easy to carry the
wrong habit across. Write the body in a file and check it with `gh issue view`
after posting if in doubt.

Do not link a test file that has not been pushed yet; the link 404s at the
moment the issue is most likely to be read. Name the path as plain text and
add the link after pushing.

## 6. Hand back

Report the mechanisms, not the tally, and name the columns whose reaction the
control proved unrelated.

Leave commits to the user; when they ask, the natural split is by what the
change is *about*:

1. the test file plus the result TOMLs for it — reference the issue here
2. any adapter or evaluator change the work required (independent bug fixes)
3. unrelated result drift the re-run happened to fix, kept separate so it does
   not read as caused by this test

## Checklist

- [ ] Group chosen on vendor status, not on subject matter
- [ ] One variable per probe
- [ ] `// V` control repeating each operation without the feature
- [ ] A `// T` marker under every annotated declaration
- [ ] Diagnostics that reveal the wrong reading marked `// Q`, not `[noise]`
- [ ] Full re-measurement after the final edit to the test file
- [ ] `errors_diff` read for control rejections before trusting `enforced_lines`
- [ ] Issue filed only if the finding is an ecosystem question
