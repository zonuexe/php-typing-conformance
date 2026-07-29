# Analyzer Adapters

## Purpose

Every tool in the matrix answers the same question — *what do you report on this
file?* — but no two answer it the same way. They differ in how they are invoked,
what they print, which exit code means "found something", how they say which
version they are, and whether they can be told which PHP version to read the
file as.

This document records what each adapter does and why, so that:

- a reader of the report can tell an analyzer's verdict from a harness artifact,
- a contributor adding a tool knows the shape to fill in,
- a tool author can see what this suite (and, by extension, any tool that drives
  analyzers programmatically) needs from a CLI.

Adapters live in [`conformance/src/Checker/`](../conformance/src/Checker/). Every
claim below is drawn from that code; when the two disagree, the code is right.

For a tool-by-tool comparison of the raw interfaces themselves — independent of
how this repository's adapters cope with them — see
[`analyzer-cli-interfaces.md`](analyzer-cli-interfaces.md).

## The adapter contract

```php
interface Checker
{
    public function name(): string;
    public function version(): string;

    /** @return array<int, list<string>> */
    public function analyse(TestCase $testCase): array;
}
```

`analyse()` returns diagnostics keyed by **1-based line number**, each formatted
as `message [code]` (or just `message` when the tool emits no code). Three rules
apply to every adapter:

1. **Only the primary test file counts.** A test case may carry support files
   (`conformance/fixtures/`, `_`-prefixed companions); they are passed to the
   tool so cross-file symbols resolve, and every diagnostic that does not land in
   the primary file is dropped.
2. **Lines are normalized to 1-based.** LSP and Mago report 0-based lines; the
   adapter adds one.
3. **Noise that is not a type verdict is dropped explicitly**, by code where the
   tool provides one, and never silently — each exclusion is justified in the
   adapter's docblock.

The harness does not ask a tool to be correct, only to be legible. A tool that
stays silent is recorded as silent.

## Reporting a version

The report prints each tool's version verbatim, so the adapter's job is to get a
single meaningful line out of the tool and stop there.

| Tool | How it is asked | What comes back | Normalization |
| --- | --- | --- | --- |
| PHPStan | `--version` | `PHPStan - PHP Static Analysis Tool 2.2.6` | none |
| Psalm | `--version` | `Psalm 6.16.1@f1f5de59…` | none |
| Phan | `--version` | three lines: Phan, php-ast, host PHP | drops a `php-ast is not installed` line |
| Mago | `--version` | `mago 1.45.0` | none |
| mir | `--version` | `mir 0.62.0` | ANSI escapes stripped |
| NoVerify | `version` (subcommand) | one long line with build date, OS and commit | none |
| Steins | `version` (subcommand) | four-line banner | takes the `steins …` line, strips the trailing ` - <url>` |
| phpy | `--version` | bare `0.2.0` | prefixed with `phpy ` |
| Intelephense | *(nothing)* | — | read from `node_modules/intelephense/package.json` |
| pzoom | *(nothing)* | — | pinned to the announced release in the adapter |

Two of these are worth naming as anti-patterns for tool authors. A CLI that
answers only to a `version` **subcommand** breaks every caller that reaches for
`--version` first, and a tool with **no version output at all** forces its
version to be hard-coded by hand — which then goes stale silently, because
nothing in the pipeline can detect the drift.

Steins additionally accepts any unknown flag without complaint, so `--version`
"succeeds" while printing nothing. Silently ignoring unknown flags makes a CLI
untestable from the outside: the caller cannot distinguish *supported and
applied* from *not supported*.

## Stating the PHP version

The corpus uses PHP 8.4+ syntax, so a tool reading it as an older version reports
parse noise instead of the type verdict under test. Every knob that exists is set
to 8.5 — see [the README](../README.md) for the policy and
[AGENTS.md](../AGENTS.md) for the maintenance rules.

| Tool | Knob | Where it is set |
| --- | --- | --- |
| PHPStan | `phpVersion: 80500` | `conformance/phpstan.dist.neon`, `conformance/phpstan-no-strict.neon` |
| Psalm | `phpVersion="8.5"` | `conformance/psalm.xml` |
| pzoom | same attribute | reads Psalm's config |
| Phan | `--target-php-version 8.5` | `PhanChecker` |
| Mago | `--php-version 8.5` | `MagoChecker` (global flag, before `analyze`) |
| mir | `--php-version 8.5` | `MirChecker` |
| Intelephense | `environment.phpVersion` | `intelephense-client.mjs`, over LSP |
| NoVerify | — | only `--php7`, a boolean |
| phpy | — | no option |
| Steins | — | no option (and unknown flags are ignored) |

Psalm deserves a warning of its own. With no `phpVersion` in the config it infers
one from `require.php` in the nearest `composer.json`, walking a candidate list
from 5.4 upward and taking the **lowest** version the constraint admits — so
`">=8.2"` means *analyse as 8.2*, not *as at least 8.2*. That is why the suite
states the version in `psalm.xml` and keeps the corpus version out of
`require.php` entirely.

## Per-adapter notes

### PHPStan — two columns, one binary

`phpstan` and `phpstan-strict` are the same executable under different configs:
the plain column uses `phpstan-no-strict.neon`, the strict column
`phpstan.dist.neon` with `strictRules.allRules: true`. Both run at `--level=max`
with `--error-format=raw`, which prints `<path>:<line>:<message>`.

Only the plain column resolves levels. Because PHPStan's levels enable rule sets
rather than inference tiers, each diagnostic is tagged with the lowest level that
reports it (`[reported-from-level=N]`). Resolving that per test would cost ~585
invocations, so the ladder is walked **once for the whole corpus** — ten runs,
indexed by file and message — while the authoritative pass stays per test. A
message the shared index has never seen (project-wide rules mean something
different when the project is one file) falls back to a per-test walk.

Exit codes 0 and 1 are both normal.

### Psalm — JSON, and a config that answers back

`--output-format=json` with `--config`; issues are matched on `file_path` and
keyed by `line_from`, formatted as `message [IssueType]`. Exit code 2 means
issues were found and is treated as success.

### pzoom — a Psalm port folded into Psalm's column

pzoom reads the same `psalm.xml` and aims at Psalm compatibility, so the report
merges it into the `psalm` column and surfaces it only where the two diverge. Its
`--format json` is not wired up, so the adapter parses console lines of the form
`ERROR: <IssueType> - <path>:<line>:<col> - <message> (see <url>)`, dropping the
trailing link. Paths come back relative, so matching is by basename. The binary
is located by `PZOOM_BIN` when set.

### Phan — a directory plus the files

Phan is invoked with `--directory conformance/tests` *and* the test's own paths,
in `--output-mode text` (`<path>:<line> <IssueType> <message>`), with
`--allow-polyfill-parser` so it runs without the `php-ast` extension. Output is
filtered by absolute-path prefix.

### Mago — workspace-relative paths, chatty stream

Mago takes a `--workspace` (here `conformance/`) and rejects paths outside it, so
the adapter converts absolute paths to workspace-relative and raises if a path
escapes. `--colors never` is required: Mago colorizes regardless of TTY.

The JSON report is not alone on the stream — since 1.45 Mago prints `INFO` lines
both before it (`Overriding workspace directory with …`) and after it (`No issues
found.`). The payload is therefore cut from the first `{` to the last `}` rather
than taken from the first brace to the end, and a run with no JSON at all but a
`No issues found.` line is read as empty. Each issue's annotations are searched
for the one of kind `Primary`, whose `span.start.line` is 0-based.

### mir — the two-path trick

Given a single input path, mir walks up to find a Composer root and hard-exits
with code 2 when that `composer.json` has no `autoload` section — which is the
case here. Passing two or more paths puts mir in its plain flow (bundled stubs,
no Composer discovery), so the adapter always prepends a neutral empty anchor
file and filters the anchor's diagnostics back out. Output is
`<file>:<line>:<col> <severity>[<code>] <name>: <message>`, with ANSI escapes
stripped from both the analysis and the version output.

### NoVerify — JSON hidden in a stream

`check --output-json --full-analysis-files <files> <target>`; the JSON document
is found as the first line beginning with `{`. Reports are matched on `filename`
and formatted as `message [check_name]`. Exit code 2 means issues were found.

### Intelephense — a language server, not a CLI

Intelephense ships no CLI, so the suite drives the server over stdio with a
minimal LSP client, [`intelephense-client.mjs`](../conformance/src/Checker/intelephense-client.mjs):

1. copy the test's files into a private temp workspace — the server indexes its
   root, so pointing it at `conformance/tests` would pull in the whole corpus;
2. `initialize` with a scratch `storagePath` and `clearCache`;
3. push settings via `workspace/didChangeConfiguration`, **and** answer
   `workspace/configuration` requests with the same object — a server may ask
   rather than accept a push, and a setting only honoured on one of the two paths
   is a setting that silently does not apply;
4. `didOpen` every file and collect `publishDiagnostics`;
5. finish after a 1.5 s quiet period following the target's diagnostics, capped
   at 45 s — a push-based protocol never says "that is all I have to say".

Diagnostics are 0-based and get `+1`. `P1003` ("symbol is declared but not used")
is dropped: these fixtures exist to exercise a signature, not to consume every
parameter.

### phpy — private workspace, message-text filtering

phpy (DEVSENSE's PHP Tools engine as a Node CLI) indexes everything under its
root, defaulting to the working directory, so each test runs in a private temp
workspace with that workspace as cwd. Output lines are
`file://<path>(<line>, <col>): <message>`; phpy resolves symlinks in the paths it
prints, so matching is by basename.

phpy attaches no code to its diagnostics, which leaves message-text matching as
the only way to drop noise. Two patterns are excluded, both verified against
every recorded result before being added rather than guessed: the
unused-assignment notice, and the "call in global namespace" performance hint.
A tool that emits stable diagnostic identifiers spares its consumers this.

### Steins — a proof layer, run wide on purpose

Steins reports only provable runtime breakage, so silence from it is an honest
verdict rather than a miss. The adapter runs `check --profile contracts` because
the default profile is deliberately proof-layer-only, which would hide the
contract-layer findings many cases assert on. `--format json` yields
`findings[]` with `path`, `line` and `id`.

`vendor/bin/steins` is a PHP launcher that downloads the platform binary on first
use and reports progress on stderr, so stderr is dropped everywhere in this
adapter — it must never reach the parsed output. `STEINS_BIN` points the suite at
a local build.

### Qodana — a report to read, not a tool to run

The only adapter that runs nothing. Qodana is proprietary and cannot be
shipped here, so the column is measured by hand from PhpStorm, using the IDE's
[Run Qodana in the IDE](https://www.jetbrains.com/help/qodana/quick-start.html#quickstart-run-in-ide)
action; `QodanaChecker` reads the `qodana.sarif.json` the IDE leaves in a
temporary directory, and `QodanaSarifReport` normalises it.

This is deliberately not [qodana-cli](https://github.com/JetBrains/qodana-cli),
which is a separate artifact with its own versioning and its own Qodana Cloud
licensing. The `linter:` field in `qodana.yaml` pins a version of that CI
container, and nothing here ever runs it.

Everything else follows from having no binary to invoke. The configuration is
pinned instead, and it takes two files that do not compose the way one would
guess: the effective inspection set is `(qodana.yaml profile + include)`
intersected with `.idea/inspectionProfiles/Project_Default.xml`. The named
profile sets the ceiling and is the only place an inspection can be added; the
project profile can only subtract. Marking something `enabled="true"` there
does nothing if `qodana.starter` does not carry it.

Four things about the report itself are worth knowing. `tool.driver.rules` is
empty — rule metadata lives in `tool.extensions[].rules`, split across 48
plugins. Everything in the file is localised, taxonomy included, so
inspections are selected by an explicit list of ASCII ids rather than by
taxon. `runs[0].properties["qodana.promo.results"]` is a rotating sample of
what the profile leaves disabled, not a result set, and is ignored. And the
sibling `qodana-short.sarif.json` carries the same summary counts over an
empty `results` array, so reading it would record a clean run.

Report selection is by modification time: PhpStorm numbers runs
`qodana_output`, `qodana_output1`, … and wipes the set on restart, so the
counter resets and the unsuffixed directory becomes newest again.

Two properties every other adapter gets for free have to be asserted here. The
report's git revision is compared against HEAD, because a stale report will
answer for test files that have changed underneath it. And a file the report
never mentions is recorded as clean — SARIF carries no list of what was
analysed, so "inspected and silent" is indistinguishable from "never
inspected", and the whole-project scan makes the former far likelier.

PhpStorm anchors PHPDoc diagnostics to the `@param` or `@return` line rather
than the declaration below it, which the corpus already accommodates:
`ExpectationParser::docblockAbove()` extends each `// T` marker over the
docblock above it, so an "undefined class `non-empty-string`" on the tag line
records as unrecognized rather than as a false positive.

## What the suite wants from a CLI

Collected from the friction above, in rough order of how much work each one saves
a caller:

- **Machine-readable output** behind a flag, complete on its own stream. If
  progress or informational lines share that stream, the payload has to be cut
  out by brace-matching — which works, but only because someone noticed.
- **A stable identifier per diagnostic.** Without one, filtering noise means
  matching English message text, which breaks on any wording change.
- **Absolute paths, or paths that match what was passed in.** Symlink resolution
  and relative output both force basename matching, which cannot distinguish two
  files of the same name.
- **Documented exit codes**, distinguishing "clean", "found issues" and "failed
  to run". Here 0/1 usually means the first two, but Psalm and NoVerify use 2 for
  "found issues" and mir uses 2 for "could not start".
- **No color unless the output is a terminal**, or a flag to turn it off.
- **Analyse what you were given.** Two tools index their whole working directory
  regardless of the paths on the command line, which is why two adapters build a
  private workspace per test.
- **`--version`**, printing one line, with no subcommand required.
- **A way to state the target PHP version**, and an error — not silence — when a
  flag is not understood.
