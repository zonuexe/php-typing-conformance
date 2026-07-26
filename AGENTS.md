# Development Notes

## Repository Purpose

This repository builds a PHP static-analysis conformance suite inspired by `python/typing` conformance testing, but adapted to PHP language features, PHPDoc semantics, and analyzer-specific behavior.

## Current Directory Layout

```text
.
├── AGENTS.md
├── CONTRIBUTING.md
├── Makefile
├── README.md
├── conformance/
│   ├── composer.json
│   ├── fixtures/
│   ├── phpstan.dist.neon
│   ├── phpstan-no-strict.neon
│   ├── psalm.xml
│   ├── results/
│   ├── src/
│   ├── templates/
│   └── tests/
├── docs/
│   └── design-doc.md
├── references/
│   ├── fig-standards/
│   ├── mago/
│   ├── mir/
│   ├── intelephense.wiki/
│   ├── noverify/
│   ├── phan.wiki/
│   ├── phpDocumentor/
│   ├── phpstan/
│   ├── psalm/
│   └── python-typing/
├── vendor/
│   ├── bin/
│   └── bamarni/composer-bin-plugin
└── vendor-bin/
    ├── mago/
    ├── mir/
    ├── intelephense/
    ├── noverify/
    ├── phan/
    ├── phpstan/
    └── psalm/
```

## What Lives Where

- `conformance/tests/`: one PHP test case per file, with inline expectations.
- `conformance/fixtures/`: support files loaded together with a primary test case.
- `conformance/src/`: discovery, expectation parsing, checker adapters, result persistence, and report generation.
- `conformance/results/`: per-tool TOML results and `version.toml` (committed), plus the generated `index.html`, `tests/*.html` and `report.css` (git-ignored; built by `make render-report-html` and by CI).
- `docs/`: design and architecture documents.
- `references/`: upstream specs, docs, and source trees used for behavior research and citations.
- `vendor-bin/`: one isolated Composer environment per analyzer.
- `vendor/`: root Composer plugin dependencies and shared bin shims.

## Tool Installation Rules

The normal binary path pattern is:

```text
vendor-bin/<tool>/vendor/bin/<tool>
```

Concrete paths currently used:

- `vendor-bin/phpstan/vendor/bin/phpstan`
- `vendor-bin/psalm/vendor/bin/psalm`
- `vendor-bin/phan/vendor/bin/phan`
- `vendor-bin/mago/vendor/bin/mago`
- `vendor-bin/mir/vendor/bin/mir`

Intelephense is a Node LSP server (not a CLI). Install it with `make install-intelephense` (npm), and it is driven through the LSP client at `conformance/src/Checker/intelephense-client.mjs`. Binary: `vendor-bin/intelephense/node_modules/.bin/intelephense`.

## NoVerify Exception

NoVerify is bootstrapped differently.

Bootstrap command:

```text
./vendor-bin/noverify/vendor/bin/noverify-get --version 0.5.5
```

Installed executable after bootstrap:

```text
vendor/bin/noverify
```

Operationally:

- do not assume `vendor-bin/noverify/vendor/bin/noverify` exists,
- invoke NoVerify from `vendor/bin/noverify`,
- if results differ unexpectedly, confirm the installed NoVerify version first.

## References

Current external reference checkouts:

- `references/python-typing/`: upstream inspiration for grouped conformance tests and reporting
- `references/fig-standards/`: FIG and PSR reference material
- `references/mago/`: Mago source and docs
- `references/mir/`: mir (Rust-based, Psalm-inspired PHP analyzer) source; the analyzer binary itself is installed via Composer (`miropen/mir-php`) into `vendor-bin/mir/`
- `references/intelephense.wiki/`: Intelephense docs (type system, config); the server is installed via npm into `vendor-bin/intelephense/`
- `references/phpDocumentor/`: PHPDoc semantics and conventions
- `references/phpstan/`: PHPStan source and website docs
- `references/psalm/`: Psalm source and docs
- `references/phan.wiki/`: Phan wiki mirror
- `references/noverify/`: NoVerify source and docs

These submodules are intentionally heavy upstream repositories. After cloning, initialize them with:

```text
make init-submodules
```

That target uses sparse checkout for the documentation-oriented repositories. `references/python-typing/` is initialized with `--filter=blob:none` only and is not sparse-checked out.

## Conformance Workflow

- Full run: `php conformance/src/main.php`
- HTML-only regeneration from existing TOML results: `make render-report-html`
- Main report output: `conformance/results/index.html` (or `make serve`, which renders the same pages per request)
- Report stylesheet: authored in `conformance/templates/report.css`, copied to `conformance/results/report.css` on every render and linked from the index and the detail pages

Current checker columns in the report:

- `phpstan`
- `phpstan-strict`
- `psalm`
- `mago`
- `mir`
- `intelephense`
- `phan`
- `noverify`

PHPStan handling is intentionally split:

- `phpstan`: non-strict config, persists max-level output, and resolves the reporting level of each individual diagnostic
- `phpstan-strict`: strict-rules config at max only

### PHPStan Levels Are Rule Sets, Not Type-Support Tiers

`conf/config.level*.neon` in phpstan-src only toggles reporting parameters
(`checkFunctionArgumentTypes` at 5, `checkMissingTypehints` at 6, `checkNullables`
at 8, `checkExplicitMixed` at 9, …). Type resolution is the same at every level.
So a level number never answers "how well does PHPStan model this type" — only
"which rule has to be enabled before PHPStan says anything".

The report reflects that:

- Each diagnostic is tagged with its own `[reported-from-level=N]`, resolved by
  re-running the file per level and recording where that exact message first
  appears. Do not stamp one file-wide number onto every message: a file can mix
  a level-2 `parameter.unresolvableType` with a level-6 `missingType.parameter`.
- `expected_diagnostic_level` in the result TOML is the lowest level on a line
  the test actually expects a diagnostic on. Unexpected noise elsewhere in the
  file does not set it, so `Fail` rows generally carry no level at all.
- The cell tag reads `reported from level N`, and the report legend states that
  levels gate rules rather than inference.

### Cell Vocabularies

The matrix carries two vocabularies, documented in the report legend:

- **Verdict** (`Pass` / `Fail`) — computed from inline expectations. Used by
  every row without `// T` markers.
- **PHPDoc type handling** — used by rows whose test carries `// T` markers.
  Derived, not curated. See below.

### Recognition And Enforcement Are Separate Questions

A test that probes a PHPDoc type spelling is really asking two things, and one
word cannot carry both:

- **Recognition** — does the analyzer resolve the spelling at all? Answered on
  the `// T` lines: an analyzer that does not know the dialect reports an
  unresolvable type, an undeclared type, or a docblock parse error right there.
  Level-independent for every analyzer.
- **Enforcement** — does it then reject the values the spelling excludes?
  Answered on the `// E` lines. Level-gated for PHPStan.

The old single `Full support` / `Not supported` label blurred the two: it could
be read as "accepts the type name" or as "warns when a value is out of range",
and `Full support (Lv 5+)` made the ambiguity worse by attaching a level to a
word that might mean either. It also could not describe a file that probes
several spellings at once — Phan resolves `scalar` but not `number`/`numeric`,
so one label had to summarize three different answers.

Both facets are now derived by `ExpectationEvaluator` and stored per result:

| TOML key | Meaning |
| --- | --- |
| `recognition` | `recognized` / `unrecognized` |
| `enforcement` | `enforced` / `partial` / `none` |
| `enforced_lines` | `n/m` — expected violation lines actually reported |
| `unrecognized_lines` | `// T` lines the analyzer complained about |
| `false_positive_lines` | reported lines that are neither expected nor marked |

Only the reason a *recognized* type goes unenforced stays hand-curated in
`status`, because the harness cannot derive it:

- `Falls back to X` — renders as `Widened to X`
- `By design` — renders as `Not enforced (by design)`; link the upstream issue
  in `notes`

Do not reintroduce `Full support` or `Not supported` as `status` values.

### Reading An Unexpected Combination

`unrecognized` together with `enforced` is not a contradiction and not a bug in
the harness. An analyzer that resolves `int-range<0, 255>` as a nonexistent
class rejects *every* argument, valid ones included — so it hits the violating
line for the wrong reason. The detail page labels that enforcement
"incidental", and the valid call shows up under `false_positive_lines`.

## Test Authoring Conventions

- Keep one primary idea per test file.
- Use file-scoped namespaces to avoid cross-test symbol collisions.
- Put companion definitions in `conformance/fixtures/` when the main test needs support code.
- Use inline expectation markers rather than external expectation files.
- Prefer required expectations (`// E`) for stable checker behavior and optional expectations (`// E?`) when a diagnostic is tool- or version-sensitive.
- Use tool-specific expectations when only one analyzer should report a diagnostic, for example `// E<psalm>`.
- Mark the declaration of any PHPDoc type spelling under test with `// T: <spelling>`, for example `function acceptsByte($value): void // T: int-range<0, 255>`. The marker turns the row into a recognition/enforcement row and stops "your dialect is unknown to me" diagnostics from counting as unexpected errors. It also covers the docblock directly above the declaration, since analyzers disagree about which of the two lines to blame.
- Do not add `// T` to a test that is about *reporting* behaviour rather than spelling recognition — `phpdoc_advanced_param_typehint_nullable_mismatch` uses ordinary types and stays a Pass/Fail row.
- Tag opinionated/advisory tests (PHPStan strict-rules, deprecations, doc conventions — anything with no runtime-safety impact) with a `@conformance-kind style` line in the leading docblock. Untagged tests default to `soundness`. The HTML report splits these into two tables: a soundness matrix (Pass/Fail) and a style matrix that only shows whether each analyzer opts into reporting the rule.

## Python-To-PHP Porting Notes

When adapting ideas from `references/python-typing/`, do not copy test shapes mechanically. The useful transfer is the test philosophy, not the syntax.

- Preserve the `python-typing` structure of small, focused, grouped tests with inline expectations.
- Translate Python concepts to the nearest PHP concept instead of forcing 1:1 feature parity.
- Treat PHP as a mixed-spec environment: native language rules, PHPDoc conventions, and analyzer extensions all matter.
- Prefer explicit source categories such as `language`, `phpdoc`, `ecosystem`, `extension`, and `historical` when adding new groups or tests.

Important mappings that have worked well so far:

- `TypedDict`-style cases map to PHP array shapes and list/non-empty-list cases.
- `Protocol`-style cases map to interfaces, object compatibility, and intersection types.
- narrowing cases map to `instanceof`, null guards, assertions, and analyzer-specific flow reasoning.
- generic conformance cases map mostly to PHPDoc templates rather than native syntax.
- distribution/support-package cases map to fixture support files and stub-like companion definitions.

Known PHP-specific pitfalls:

- PHP has no single canonical typing spec equivalent to Python typing behavior; tool divergence is expected.
- Some behaviors are intentionally not reported by some tools and should be recorded as `By design`, not as regressions.
- PHPDoc syntax is richer and less standardized than native PHP syntax, so tests involving shapes, generics, callable signatures, aliases, and nullability need tool-specific validation.
- Historical PHP behaviors such as implicit nullability and legacy resources deserve their own coverage instead of being folded into modern native-type cases.
- Parse-time invalid syntax and analysis-time type errors can coexist in one file; some analyzers stop earlier than others.

Practical guidance:

- Start from a minimal PHP example, then add only the smallest PHPDoc or language feature needed to preserve the original conformance question.
- Prefer tests that expose checker differences cleanly over tests that combine many PHP-specific features at once.
- When a Python case depends on library distribution behavior, consider whether it should become a fixture-based support file test, a stub test, or be skipped entirely.
- If a ported case ends up testing only one tool's extension, place it under the appropriate PHP-specific group instead of pretending it is language-level conformance.

## Development Conventions

- Prefer tool-local binaries over globally installed commands.
- Treat `vendor-bin/<tool>/composer.lock` as the authoritative version pin for that analyzer.
- Treat `references/` as source material, not as a place to edit project logic.
- Put architecture-level decisions in `docs/design-doc.md`.
- Put contributor workflow and repository-specific cautions in `AGENTS.md`.
