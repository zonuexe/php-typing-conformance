# Corpus divergence sweep

A second track, complementary to the hand-authored tests in `conformance/tests/`. Instead
of writing cases ourselves, this reads the large test corpora that each analyzer ships and
replays them across **all** tools to find where behavior diverges — testing the hypothesis
that an edge case one analyzer handles is latent in the others.

See also `docs/regression-edge-cases.md` (the hypothesis and the hand-authored regression
group that confirmed divergences get promoted into).

## No redistribution: read in place, stage transiently

Third-party test files are **not** copied into this repository. `sweep.php` reads each
case from its origin checkout, copies it into an isolated scratch workspace under the
system temp dir for the duration of a single analysis, then deletes it. Only the resulting
**behavioural facts** (which tool diverges on which case, by normalized category) are ever
persisted here. This keeps the corpora under their own licenses (PHPStan MIT, Psalm MIT,
Mago Apache-2.0/MIT) without vendoring or re-licensing their code.

Because nothing is committed, the corpora are environment-specific inputs. Point the sweep
at a local checkout via `--cases-dir`. Known local clones on the maintainer's machine:

- Mago: `/Users/megurine/repo/rust/mago/crates/analyzer/tests/cases`
  (the `references/mago` submodule is sparse-checked-out to `docs` only)
- PHPStan: `/Users/megurine/repo/php/phpstan-src`
- Psalm: `/Users/megurine/repo/php/psalm`

## Baseline

Each corpus states its owning tool's expected verdict differently:

- **Mago** — inline `@mago-expect analysis:<code>` pragmas. Absence ⇒ the case is expected
  to be clean (a false-positive regression fixture). Presence ⇒ Mago expects those codes.

The sweep takes that owning-tool verdict as the baseline and compares every other tool
against it:

- **FP** — baseline is clean, but a compared tool reports a *soundness* diagnostic.
- **MISS** — baseline expects an error, but a compared tool reports nothing in the
  corresponding soundness category.

NoVerify is excluded from comparison (shape-blind; always noisy on modern PHPDoc).

## Classifier

`categories.json` maps each tool's diagnostic code to a normalized category with a
`soundness` flag. Only soundness categories (argument-type, return-type, narrowing,
undefined-index, …) count as divergences by default; style, parser-capability, unused, and
tool-specific-heuristic categories are dropped as noise. Unmapped codes are listed at the
end of every run so the map can grow. Pass `--all-categories` to disable filtering.

## Usage

```sh
php conformance/corpus/sweep.php \
  --cases-dir=/Users/megurine/repo/rust/mago/crates/analyzer/tests/cases \
  reconcile_non_empty_string string_reconciliation array_reconcile
```

Confirmed soundness divergences should be distilled into a minimal, self-authored
`conformance/tests/regressions_*.php` case (with `// E<tool>` markers) — do not copy the
corpus file. That committed test is where the divergence becomes a tracked, first-party
fixture.
