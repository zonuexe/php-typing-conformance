# Results

One TOML file per `<tool>/<test>.toml`, plus `updated.toml`. These are the
report's source data and are committed; the HTML around them is not.

`updated.toml` records when the results last changed: `updated_at`, an ISO 8601
stamp the report prints verbatim, and `data_digest`, a hash over the test set,
the tool versions and every result. Nothing else can tell how fresh the
comparison is -- a clone resets file timestamps, and the pages are rebuilt on
every publish.

The runner rewrites the stamp only when that digest moves. A run that
re-derives the same results for the same tests at the same versions leaves it
alone, so re-running the suite neither dirties the working tree nor claims the
comparison is fresher than it is.

`index.html`, `report.css`, `ogp.png` and the per-test pages under `tests/`
are built from these files by `make render-report-html`, and rebuilt by GitHub
Actions on every push to master before the site is published, so nothing
generated is kept under version control.

Most keys are regenerated on every run. Two are hand-curated and preserved
across runs by `ResultRepository::save()`: `status` and `notes` (plus
`ignore_errors`). Everything else is derived, so editing it by hand is pointless.

| Key | Written by | Meaning |
| --- | --- | --- |
| `conformance_automated` | derived | `Pass` / `Fail` against the inline expectations |
| `expected_diagnostic_count` | derived | number of `// E` markers |
| `output` | derived | the analyzer's diagnostics, one per line |
| `errors_diff` | derived | how the output differs from the expectations |
| `expected_diagnostic_level` | derived, PHPStan only | lowest level whose *rules* report an expected diagnostic |
| `recognition` | derived, `// T` tests | `recognized` / `unrecognized` |
| `enforcement` | derived, `// T` tests | `enforced` / `partial` / `none` / `no-probes` |
| `enforced_lines` | derived, `// T` tests | `n/m` expected violation lines reported |
| `unrecognized_lines` | derived, `// T` tests | `// T` lines with a type-resolution failure |
| `false_positive_lines` | derived, `// T` tests | reported lines that are neither expected nor marked |
| `over_rejected_lines` | derived, `// T` tests | valid-control lines rejected with a type mismatch |
| `status` | curated | `Falls back to X` or `By design` — see below |
| `notes` | curated | free text, rendered as a hover card; links are linkified |

## `status`

Only two values are still meaningful, both explaining something the harness
cannot derive:

- `Falls back to X` — the analyzer resolved the spelling but widened it to `X`.
  Renders as `Widened to X`, including when leftover probes of the base type
  still fire.
- `By design` — the analyzer could report and deliberately does not. Renders
  as `Not enforced (by design)` when nothing is rejected, or
  `Unsound (by design, n/m)` when some probes fire (PHPStan's benevolent
  union on `array-key`). Put the upstream write-up in `notes`.

`Full support` and `Not supported` were retired: recognition and enforcement are
now derived from `// T` and `// E` markers, and a single word could not say
which of the two it meant. See AGENTS.md for the full rationale.
