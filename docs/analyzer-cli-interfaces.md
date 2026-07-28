# Analyzer CLI Interfaces: A Comparison

## Scope

This document compares the command-line (and, for Intelephense, protocol)
interfaces of the ten tools tracked by this repository, as those interfaces
behave in the versions currently pinned (see each tool's `results/<tool>/version.toml`).
It is a comparison of the tools themselves, independent of how this repository
happens to drive them — for that, see
[`analyzer-adapters.md`](analyzer-adapters.md), which documents the workarounds
each interface quirk below required, plus the exact commands this repository's
harness runs.

Every row is drawn from a command actually run, a `--help` screen actually read,
or an output file actually produced during this repository's own use of the
tool. Nothing here describes a tool's full feature set — only the surface this
comparison exercised.

## Tools at a glance

| Tool | Project | Runtime | Interface |
| --- | --- | --- | --- |
| PHPStan | phpstan/phpstan | PHP (Composer) | CLI binary |
| Psalm | vimeo/psalm | PHP (Composer) | CLI binary |
| pzoom | muglug/pzoom | Rust | CLI binary, Psalm-config-compatible |
| Phan | phan/phan | PHP (Composer; needs `php-ast` or a polyfill) | CLI binary |
| Mago | carthage-software/mago | Rust | CLI binary, subcommand-based |
| mir | miropen/mir-php | Rust | CLI binary |
| NoVerify | VKCOM/noverify | Go | CLI binary, subcommand-based |
| Intelephense | bmewburn/intelephense | Node | LSP server over stdio — no CLI at all |
| phpy | DEVSENSE/phpy | Node (wraps a native engine) | CLI |
| Steins | rigortype/steins | Rust, via a PHP launcher | CLI binary, subcommand-only |

Two shapes stand out against the other eight. Intelephense has no
argv-driven "analyze this and print" mode; the only way to get a diagnostic out
of it is to speak the Language Server Protocol. And three tools — Mago,
NoVerify, Steins — take subcommands (`analyze`, `check`, `check`) rather than
doing their one job when invoked bare with flags.

## Pointing it at code

Tools differ in what unit they want: a list of files, a directory, or a
workspace root — and in whether the file list you give them is the whole scope
or just a filter over a larger one.

| Tool | You give it | Notes |
| --- | --- | --- |
| PHPStan | a list of file/directory paths | positional arguments, appended after flags |
| Psalm | a list of file paths | restricts analysis to those files; project context still comes from `--config` |
| pzoom | a list of file paths | same shape as Psalm |
| Phan | `--directory <dir>` *and* a list of files | the directory sets the indexed project; the file list narrows which files' issues print |
| Mago | `--workspace <dir>` *and* workspace-relative file paths | paths outside the workspace are rejected outright |
| mir | a list of file paths | **one** path makes mir walk up to find a Composer root; **two or more** paths switch it to a bundled-stub flow with no Composer discovery — the same binary scopes itself differently depending on argument count |
| NoVerify | `check --full-analysis-files <files...> <path>` | a file list plus a trailing path argument |
| Intelephense | `rootUri` / `workspaceFolders` at `initialize`, then `didOpen` per file | the whole workspace is indexed; diagnostics stream per opened file, not per request |
| phpy | `-r/--root <path>` (default: cwd) | indexes **everything under root**, regardless of which files were named on the command line |
| Steins | a list of file paths | treated as one project per invocation; no directory/workspace flag observed |

phpy's behavior is worth calling out on its own: pointing it at one file while
running from an unrelated working directory does not limit it to that file — it
walks whatever tree its root happens to be.

## Config files

| Tool | Config file | Format | How it's selected |
| --- | --- | --- | --- |
| PHPStan | `phpstan.neon` (or any name) | Neon | `-c <path>` |
| Psalm | `psalm.xml` | XML | `--config=<path>` |
| pzoom | Psalm's `psalm.xml` | XML | `--config=<path>` — same file, same format |
| Mago | none required | — | all state passed as flags in the invocations observed |
| Others (Phan, mir, NoVerify, Intelephense, phpy, Steins) | none used | — | all state passed as flags, or (Intelephense) LSP settings |

pzoom reading Psalm's own config format is the one case here of two otherwise
unrelated codebases sharing a config file byte-for-byte.

## Reporting a version

| Tool | How to ask | Raw output | Shape |
| --- | --- | --- | --- |
| PHPStan | `--version` | `PHPStan - PHP Static Analysis Tool 2.2.6` | one line |
| Psalm | `--version` | `Psalm 6.16.1@f1f5de59…` | one line, with commit hash |
| Phan | `--version` | `Phan 6.0.7` / `php-ast version 1.1.3` / `PHP version used to run Phan: 8.5.8` | three lines, three different things |
| Mago | `--version` | `mago 1.45.0` | one line |
| mir | `-V` / `--version` | `mir 0.62.0` | one line |
| NoVerify | `version` (subcommand — `--version` is not one) | `NoVerify, version 0.5.5: built on: … OS: … Commit: …` | one long line |
| Steins | `version` (subcommand — `--help`/`--version` are rejected as unknown commands) | a four-line banner; only the first line carries the version | multi-line |
| phpy | `-V` / `--version` | `0.2.0` | one bare token, no tool name |
| Intelephense | none | — | read from `node_modules/intelephense/package.json`; nothing to invoke |
| pzoom | none | — | no version output exists at any invocation |

Three interface choices here make a version harder to get than it needs to be:
requiring a subcommand instead of answering `--version` directly (NoVerify,
Steins); mixing the version with unrelated diagnostic information across
several lines instead of one (Phan); and shipping no version-reporting surface
at all (pzoom, and Intelephense's CLI-less design).

Steins additionally accepts flags it does not recognize without complaint —
`steins check --this-is-not-a-real-flag foo.php` runs normally and exits 0. A
caller cannot use exit status to tell "this flag took effect" from "this flag
was silently ignored."

## Declaring a target PHP version

| Tool | Mechanism | Granularity | If left unset |
| --- | --- | --- | --- |
| PHPStan | `phpVersion` in the Neon config (no CLI flag) | `MMmmpp` integer, e.g. `80500` | falls back to the running PHP's own version |
| Psalm | `phpVersion` attribute in `psalm.xml`, or `--php-version=<X.Y>` on the CLI (confirmed via `--help`; the flag overrides the config) | major.minor | infers from `require.php` in the nearest `composer.json` — see below — or falls back to the running PHP's version if there is no such key |
| pzoom | same `phpVersion` attribute, since it reads Psalm's config | major.minor | same fallback chain as Psalm |
| Phan | `--target-php-version {8.1,8.2,8.3,8.4,8.5,native}` | a fixed enum, not an arbitrary version string | `native`, i.e. the running PHP |
| Mago | `--php-version <X.Y>` — a **global** flag, given before the subcommand | major.minor | reads its own config if present, otherwise the running PHP |
| mir | `--php-version <X.Y>` | major.minor | its own config, if any, otherwise unspecified |
| Intelephense | `intelephense.environment.phpVersion` setting, sent over LSP (`workspace/didChangeConfiguration` and/or answered from `workspace/configuration`) | full semver string, e.g. `8.5.0` | the project's own published default — documented as `7.4.0` on one wiki page and `8.3.0` in the settings schema on another, so even the tool's own documentation disagrees with itself |
| NoVerify | `--php7` (boolean only) | two-way switch, nothing finer | analyzes as its own default dialect |
| phpy | none observed | — | — |
| Steins | none observed (and unknown flags are silently ignored, so a fabricated flag does not surface as an error either) | — | — |

Psalm's fallback is the sharpest edge in this table. Absent an explicit
`phpVersion`, Psalm reads `require.php` from `composer.json`, walks a fixed
candidate list from `5.4` upward, and returns the **lowest** version the
constraint's semver range admits. A constraint of `">=8.2"` — which reads, in
prose, as "at least 8.2" — makes Psalm analyze the code **as 8.2 exactly**, not
as "whatever is at least 8.2." A composer.json written to describe the
runner's own minimum requirement will silently double as the analysis target if
nothing overrides it.

Phan is the only tool here that constrains the version to a fixed set of
values rather than accepting an arbitrary version string — useful for
validation, but it means a version newer than the enum's newest entry has no
way to be named until Phan ships an update.

## Output formats

| Tool | Format flag | Structure | Sample |
| --- | --- | --- | --- |
| PHPStan | `--error-format=raw` (also: `json`, `table`, `checkstyle`, …) | one line per diagnostic | `<path>:<line>:<message>` |
| Psalm | `--output-format=json` (also: `text`, `compact`, …) | JSON array/object of issues | fields include `file_path`, `line_from`, `type`, `message` |
| pzoom | text only — `--format json` exists but is not implemented | one line per diagnostic, plus a source snippet line | `ERROR: <IssueType> - <path>:<line>:<col> - <message> (see <url>)` |
| Phan | `--output-mode text` (also: `json`, `csv`, `checkstyle`, …) | one line per diagnostic | `<path>:<line> <IssueType> <message>` |
| Mago | `--reporting-format json` | one JSON object, `issues[]`, each with nested `annotations[]` carrying file/line spans | interleaved with plain `INFO` lines on the same stream, both before and after the JSON |
| mir | default text (colored) | one line per diagnostic | `<file>:<line>:<col> <severity>[<code>] <name>: <message>` |
| NoVerify | `--output-json` | one JSON object, `Reports[]` | JSON is one line among others on the same stream |
| Intelephense | n/a (LSP) | `textDocument/publishDiagnostics` notifications | each diagnostic carries a 0-based `range`, `message`, `code`, `severity` |
| phpy | default text | one line per diagnostic, **no diagnostic code of any kind** | `file://<path>(<line>, <col>): <message>` |
| Steins | `--format json` | one JSON object, `findings[]`, plus `suppressed`/`baselined`/`vendor_suppressed` counters | |

Three tools (Mago, NoVerify, and to a lesser extent pzoom's text mode) share
their machine-readable payload's stream with unrelated log lines, which means
a consumer has to locate the JSON by scanning for `{`/`}` rather than reading
the whole stream as one document. phpy is the one tool in this comparison whose
diagnostics carry no identifier at all — see the identifiers table below.

## Exit codes

| Tool | Clean | Findings reported | Other codes seen |
| --- | --- | --- | --- |
| PHPStan | `0` | `1` | — |
| Psalm | `0` | `2` | — |
| pzoom | `0` | `1` or `2` (Psalm-style, with some slack) | — |
| Phan | `0` | `1` | — |
| Mago | `0` | `1` | — |
| mir | `0` | `1` | `2` — hard failure to start (e.g. a single-path invocation whose Composer root has no `autoload` section) |
| NoVerify | `0` | `2` | — |
| Intelephense | n/a — process exit is unrelated to whether diagnostics were published | | |
| phpy | `0` | **also `0`** | phpy's exit status does not distinguish "nothing found" from "found something" at all |
| Steins | `0` | `1` | — |

Exit-code conventions split roughly into a PHPStan/Phan/Mago/Steins family
using `1` for "found something" and a Psalm/NoVerify family using `2` — with
mir using `2` for the opposite situation, an outright failure to run. phpy sits
outside both families: its process exit code carries no signal about whether
anything was reported, so a caller has to parse output to know.

## Diagnostic identifiers

| Tool | Carries a stable ID per diagnostic? | Field | Example |
| --- | --- | --- | --- |
| PHPStan | yes | `identifier` | `argument.type` |
| Psalm | yes | `type` | `InvalidArgument` |
| pzoom | yes | issue type in the text line | `InvalidArgument` |
| Phan | yes | issue type in the text line | `PhanTypeMismatchArgumentProbablyReal` |
| Mago | yes | `code` | `invalid-argument` |
| mir | yes | bracketed code in the text line | `MIR0201` |
| NoVerify | yes | `check_name` | `notSafeCall` |
| Intelephense | yes | `code` | `P1006` |
| phpy | **no** | — | message text only |
| Steins | yes | `id` | `phpdoc.param-mismatch` |

phpy is the outlier: with no field to key on, distinguishing one kind of finding
from another means matching substrings of the English message, which breaks the
moment the tool's wording changes.

## Color output

Not every tool's help text documents a color-control flag, so this table
reports what was actually observed on stdout/stderr, not a claim about what
options exist beyond that.

| Tool | Observed behavior |
| --- | --- |
| Mago | colorizes unconditionally; `--colors <auto\|always\|never>` exists and `never` must be passed explicitly to get plain output |
| mir | ANSI escape codes appear in output in this environment regardless of TTY |
| phpy | ANSI escape codes appear in output in this environment regardless of TTY |
| PHPStan, Psalm, pzoom, Phan, NoVerify, Intelephense, Steins | no ANSI sequences observed in the output produced during this comparison |

## Summary

No two of these ten tools share an identical interface shape, but the axes of
disagreement repeat: subcommand vs. flag for the basic "what version are you"
question, config-file vs. CLI-flag vs. protocol-message for stating a target
PHP version, one clean JSON stream vs. JSON interleaved with log lines, and
whether the exit code says anything about whether something was found. A tool
sitting on the more permissive end of each of these axes — a plain `--version`,
an explicit PHP-version flag with no silent fallback, one uncluttered
machine-readable stream, a documented exit-code contract, and a stable
identifier per diagnostic — is, all else equal, less code for anything driving
it programmatically to get right.
