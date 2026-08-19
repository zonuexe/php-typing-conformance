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
│   ├── lsp/
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
│   ├── python-typing/
│   └── laravel-gate-image-board/
├── vendor/
│   ├── bin/
│   └── bamarni/composer-bin-plugin
└── vendor-bin/
    ├── mago/
    ├── mir/
    ├── intelephense/
    ├── devsense-php-ls/
    ├── noverify/
    ├── phan/
    ├── phpactor/
    ├── phpantom/
    ├── php-lsp/
    ├── phpstan/
    ├── psalm/
    └── laravel-lsp/
```

## What Lives Where

- `conformance/tests/`: one PHP test case per file, with inline expectations.
- `conformance/fixtures/`: support files loaded together with a primary test case.
- `conformance/lsp/`: the language-server measurement's fixtures, probe definitions (`probes.toml`), Laravel extras (`laravel/`, including Gate corpus probes), and per-server workspace config; results land in `conformance/results/lsp/`.
- `conformance/src/`: discovery, expectation parsing, checker adapters, result persistence, and report generation.
- `conformance/results/`: per-tool TOML results and `version.toml` (committed), plus the generated `index.html`, `tests/*.html` and `report.css` (git-ignored; built by `make render-report-html` and by CI).
- `docs/`: design and architecture documents, plus `analyzer-adapters.md`, which
  documents each adapter's invocation, version reporting, PHP-version knob and
  output normalization for readers outside this repository.
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
- `vendor-bin/phpactor/vendor/bin/phpactor` (one install serving both axes — the `worse:analyse` matrix column and the LSP probes; its `composer.json` needs `minimum-stability: dev` + `prefer-stable` because phpactor requires `jetbrains/phpstorm-stubs dev-master`)
- `vendor-bin/devsense-php-ls/node_modules/.bin/devsense-php-ls` (npm, `make install-devsense-php-ls`; separately versioned from the copy phpy bundles)
- `vendor-bin/php-lsp/bin/php-lsp` and `vendor-bin/phpantom/bin/phpantom_lsp` (per-platform GitHub release binaries, git-ignored; `make install-php-lsp` / `make install-phpantom`)
- `vendor-bin/laravel-lsp/vendor/bin/laravel-lsp` (Composer, `laravel/lsp`; `make install-laravel-lsp`). Framework-specific: initialize requires an `artisan` file in the workspace.

Intelephense is a Node LSP server (not a CLI). Install it with `make install-intelephense` (npm), and it is driven through the LSP client at `conformance/src/Checker/intelephense-client.mjs`. Binary: `vendor-bin/intelephense/node_modules/.bin/intelephense`.

## Language-server probes

`make run-lsp-probes` measures the language servers over LSP itself
(capability handshake, one probe per capability, hover type conformance,
and — when `~/repo/php/steins-survey/psysh` exists — real-project
definition/references navigation) and writes committed TOMLs to
`conformance/results/lsp/`. Fixtures and probe definitions live in
`conformance/lsp/` (`probes.toml` for fixtures, `navigation.toml` for the
line-pinned corpus ground truth, `laravel/` for framework probes). The
Gate imageboard (`references/laravel-gate-image-board`, pinned in
`laravel/corpus.toml`) is Laravel LSP's real-project workspace: env,
config, route, view and translation probes run against
`app/Support/LspSurface.php` after `make install-laravel-corpus`.
Without the submodule the stub artisan + helpers.php session still runs.
Navigation on psysh is skipped for Laravel LSP. Method and hard-won traps
are documented in `docs/language-servers.md` — read it before touching
the probe flow.

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
- `references/laravel-gate-image-board/`: Gate, a Laravel 13 imageboard used as Laravel LSP's framework corpus (`make install-laravel-corpus` installs its vendor/)

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
- OGP image: authored in `conformance/templates/ogp.png`, copied to `conformance/results/ogp.png` on every render; Open Graph / Twitter Card tags in `page.phtml` share one description and image, with only the title per page

Current checker columns in the report, in display order:

- `phan`
- `phpstan`
- `psalm`
- `mago`
- `mir`
- `phpantom`
- `intelephense`
- `phpactor`
- `phpy`
- `qodana`
- `noverify`
- `steins`

`psalm-next` is a second Psalm installation running the next major line
(`vendor-bin/psalm-next`, currently 7.0.0-beta19) through the same adapter
and config. It is a configuration of `psalm` for the metadata and release
tables, and the evaluator makes `// E<psalm>` expectations apply to it too —
where the 7.x line diverges, that shows up as a missed or unexpected
diagnostic rather than a marker the column never saw. It is not a column of
the index matrix; the psalm version cell names the next line on its second
line (`6.16.1 / next: 7.0.0-beta19`), and the detail pages carry it as a
full row. Only the CLI is measured; there is no LSP probe for the next line.
The 7.x-only purity findings (`MissingPureAnnotation`,
`MissingAbstractPureAnnotation`) are suppressed in
`conformance/psalm-next.xml` — a copy of `psalm.xml` with the 7.x-only
handlers, since Psalm 6's schema rejects the elements — because the corpus
does not test mutation-free annotations.

`qodana` is measured by hand: run Qodana from PhpStorm (Code | Analyze Code |
Run Qodana in the IDE), then `php conformance/src/main.php --tool=qodana`,
which reads the newest report out of the system temp directory. Any test file
newer than that report is recorded as `Not measured` instead of passing, so a
newly added test case shows the gap in the matrix until the inspection is
re-run.

SonarQube / sonar-php was evaluated and declined (#10): it is a rule
catalogue, not a type checker. The adapter is kept for a repeat
measurement (`make run-sonarqube`, then
`SONAR_TOKEN=… php conformance/src/main.php --tool=sonarqube`) but it
is not a column of the index matrix or the analyzer reference table.

PHPStan handling is intentionally split:

- `phpstan`: non-strict config, persists max-level output, and resolves the reporting level of each individual diagnostic
- `phpstan-strict`: strict-rules config at max only

### The Analysis Target Is Pinned To PHP 8.5

The corpus uses PHP 8.4+ syntax (property hooks, asymmetric visibility), so
every analyzer has to be told which language version it is reading. Left to
their defaults the tools disagree, and a tool that targets an older version
reports parse noise instead of the type behavior under test.

`composer.json` (root and `conformance/`) requires `php-64bit: ^8.5` and pins
`config.platform.php` to `8.5.0`; each analyzer that exposes a knob is set to
8.5 explicitly:

| Tool | Where |
| --- | --- |
| PHPStan | `phpVersion: 80500` in both `.neon` files |
| Psalm, pzoom | `phpVersion="8.5"` in `conformance/psalm.xml` |
| Phan | `--target-php-version 8.5` |
| Mago | `--php-version 8.5` (global flag, before `analyze`) |
| mir | `--php-version 8.5` |
| Intelephense | `environment.phpVersion` in `intelephense-client.mjs` |
| Qodana | `php.version: 8.5` in `qodana.yaml`, and the IDE's own PHP language level |

NoVerify (only `--php7`), phpy and Steins expose nothing to set. Steins accepts
any unknown flag silently, so do not "set" a version there and assume it took.

Never state the corpus version through `require.php` in `conformance/composer.json`:
Psalm reads that key and walks a candidate list from 5.4 upward, taking the
*lowest* version the constraint admits — `">=8.2"` made it analyse at 8.2 and
drop every `private(set)` property.

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
- The cell tag reads `reported Lv.N+` (`Lv.max` at 10), and the report legend
  states that levels gate rules rather than inference. Unrecognized cells omit
  the tag: recognition is level-independent, and the stored level on those
  rows is usually mixed-fallout, not the unresolvable-type diagnostic.

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
| `enforcement` | `enforced` / `partial` / `none` / `no-probes` |
| `enforced_lines` | `n/m` — expected violation lines actually reported |
| `unrecognized_lines` | `// T` lines with a type-resolution failure (not style / documented-vs-declared noise) |
| `false_positive_lines` | reported lines that are neither expected nor marked |
| `over_rejected_lines` | valid-control (`// V`) or unmarked-valid lines rejected with a type mismatch |

`no-probes` is the case where the file carries no `// E` line at all, so
enforcement was never put to the question; it renders as
`Recognized (no probes)` rather than `Not enforced`, which would read as a miss.
Prefer adding probes to leaving a `// T` test at `no-probes`.

`over_rejected_lines` non-empty means the analyzer also rejected values the
type admits. The matrix then says **Incidental**, not Enforced: hits on the
`// E` lines are the wrong reason (class-name fallback, sealed where the test
asked for unsealed, over-strict purity, …). Re-derive classifications from
stored output with `php conformance/src/main.php --rescore` after changing
the evaluator; that does not re-run the analyzers.

Only the reason a *recognized* type goes unenforced stays hand-curated in
`status`, because the harness cannot derive it:

- `Falls back to X` — renders as `Widened to X`, including when leftover
  probes of the base type still fire (phpantom rejecting a float after
  reading `__benevolent<int|string>` as ordinary `int|string`)
- `By design` — renders as `Not enforced (by design)` when nothing is
  rejected, or `Unsound (by design, n/m)` when some probes fire and the rest
  are a documented hole (PHPStan's benevolent union on `array-key`). Link the
  upstream write-up in `notes`

Do not reintroduce `Full support` or `Not supported` as `status` values.

### Reading An Unexpected Combination

`unrecognized` together with `enforced` is not a contradiction and not a bug in
the harness. An analyzer that resolves `int-range<0, 255>` as a nonexistent
class rejects *every* argument, valid ones included — so it hits the violating
line for the wrong reason. The matrix says Unrecognized; the detail page
labels the E-line hits incidental.

The same incidental label is used when recognition succeeded but
`over_rejected_lines` is non-empty (mir treating `number` as a class without
complaining on the declaration, Qodana reading `...` as sealed, …). Mark
valid calls with `// V` so that case is first-class rather than an unmarked
false positive.

## Test Authoring Conventions

- Keep one primary idea per test file.
- Use file-scoped namespaces to avoid cross-test symbol collisions.
- Put companion definitions in `conformance/fixtures/` when the main test needs support code.
- Use inline expectation markers rather than external expectation files.
- Prefer required expectations (`// E`) for stable checker behavior and optional expectations (`// E?`) when a diagnostic is tool- or version-sensitive.
- Use tool-specific expectations when only one analyzer should report a diagnostic, for example `// E<psalm>`.
- Mark the declaration of any PHPDoc type spelling under test with `// T: <spelling>`, for example `function acceptsByte($value): void // T: int-range<0, 255>`. The marker turns the row into a recognition/enforcement row and stops "your dialect is unknown to me" diagnostics from counting as unexpected errors. It also covers the docblock directly above the declaration, since analyzers disagree about which of the two lines to blame.
- Mark values the spelling *admits* with `// V` (valid control). Enforcement is genuine only when those lines stay silent and the `// E` lines fire. A type-rejection on a `// V` line is over-rejection — the matrix says Incidental, not Enforced. Do not use `// Q` for this: quiet probes *count* silence as honouring a suppress tag.
- Do not put `// E?` on a declaration to mean "this spelling may not parse". That is recognition: use `// T`. An optional-only Pass/Fail row treats silence as Pass, which hides "the tool did nothing".
- A tag whose honour signal is silence (narrowing, `@param-out`, `@not-deprecated`) needs `// Q` / `// Q?`, not `// E?`. `// E?` on the honour-is-silence line makes every outcome Pass.
- Do not add `// T` to a test that is about *reporting* behaviour rather than spelling recognition — `phpdoc_advanced_param_typehint_nullable_mismatch` uses ordinary types and stays a Pass/Fail row.
- An annotation tag under test is marked the same way, `// T: @phpstan-assert`. The facets read the same as for a type spelling: recognition is "does the analyzer accept the tag", enforcement is "does it act on what the tag claims". A tag whose effect is a *narrowing* has to be probed by something the narrowing makes impossible, since ignoring it is otherwise silent — see `phpdoc_advanced_vendor_prefixed_assert_phpstan`, where an `is_string()` check becomes always-false once the assertion is applied.
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
