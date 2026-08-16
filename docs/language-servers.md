# Measuring the language servers

The report carries two language-server tables with strictly separated
epistemics. The **reference table** records what each project claims about
itself, verified against nothing. The **measured capability matrix** records
what happened when this suite launched a server headless and spoke LSP to it.
This document describes how the second one is produced, and the traps that
shaped its design.

## The three layers

The measurement asks three questions, in the same spirit as the suite's
recognition/enforcement split:

1. **Advertised** — what does the server's `initialize` response declare in
   `ServerCapabilities`? This is the langserver.org-style feature table, but
   read from the handshake of the running server rather than from anyone's
   README. Dynamic registrations (`client/registerCapability`) count as
   advertised too, and are recorded separately in the result file.
2. **Functional** — when an advertised capability is called at a position
   prepared for it, does a non-empty, well-formed answer come back? One probe
   per capability, defined in `conformance/lsp/probes.toml`.
3. **Conformant** — for hover, the one capability whose *content* is a typing
   question: hovering a variable whose type the annotation or the narrowing
   established, is the shown type the established one, a widened fallback, or
   nothing? Expected spellings and rejection patterns live next to the hover
   probes; the verbatim hover text is stored in the result either way, so a
   verdict is always auditable.
4. **Navigation on a real project** — can the server enumerate a real
   codebase's dependency graph? The corpus is the psysh checkout from
   `~/repo/php/steins-survey` (pinned to a commit, verified at load), and
   `conformance/lsp/navigation.toml` picks one symbol per kind — function,
   instance method, static method, local variable, class constant, instance
   property, static property. Go-to-definition jumps from a use site and must
   land on the declaration line; find-references asks from the declaration
   and is scored as recall against the complete, grep-assembled set of code
   references. Only the two files each probe touches are opened — the rest
   of the reference set has to come out of the server's own index, which is
   the point. The corpus plants same-name decoys (a `FakePsrLogger::info()`
   against `Psy\info()`, several `getAll()`s against `Context::getAll()`),
   so a reference implementation that matches by name instead of by symbol
   shows up as extras beyond the expected set. One kind is honestly absent:
   psysh — like much modern PHP — defines no namespace-level constant that
   any code references, so `define()`/`const` navigation waits for a corpus
   from that era (ec-cube2 in the same survey is the natural candidate).

Probes for capabilities a server did not advertise are recorded as `skipped`,
not sent: a server that does not advertise a method commonly never answers it,
and each such probe would cost a full timeout. The one deliberate asymmetry is
push diagnostics, which have no capability flag in the protocol at all — that
row is behavioural observation alone (did the session ever publish diagnostics
for the fixture holding the deliberate type error?).

## What configuration the servers get

A capability matrix is only as meaningful as the configuration behind it, and
this one has a rule: **each server is launched with the settings that turn on
its own features, and with nothing that delegates to another tool.**

Turning a server's own feature on is fair game, because a user reaches it by
editing a settings file. Phan's language server hides go-to-definition, hover
and completion behind `--language-server-enable-*` flags, and the run passes all
three. Phpactor's inlay hints are off until
`language_server_worse_reflection.inlay_hints.enable` is set, and off for the
type kind specifically until `.types` is set as well; the run sets both, since
the inferred type at a binding is this suite's whole subject. Psalm's
`--enable-*` switches all default to on, so it needs nothing.

Installing an adapter to another analyzer is a different act, and the run does
not do it. Phpactor bundles PHPStan, Psalm, Mago, php-cs-fixer and
PHP_CodeSniffer integrations in core, each behind `language_server_<tool>.enabled`;
PHPantom has its own PHPStan, PHPCS and Mago hooks. Enabling them would fill the
diagnostics and formatting rows with a *different tool's* output under this
server's name, and both of those tools already have columns of their own. The
reference table records which analyzers each server can drive, which is the
honest place for that fact.

A licence is not configuration. Intelephense omits its paid capabilities from
the handshake and DEVSENSE advertises then refuses with `-32803`; no setting
reaches them, so they stay recorded as measured.

The practical consequence: a `false` in the matrix means the server does not
offer that capability by itself, not that nobody asked it nicely.

## Moving parts

- `conformance/lsp/fixtures/` — the workspace the servers see: `lib.php` and
  `capabilities.php` for the capability probes (cross-file jumps, a rename
  target, one deliberate type error), `hover-types.php` for the hover cases.
- `conformance/lsp/probes.toml` — every probe, anchored to fixture text
  (`at` = a substring, `offset` = characters past its start) rather than to
  line numbers, so editing a fixture either moves the probes with it or breaks
  the run loudly. Never silently.
- `conformance/lsp/config/` — per-server config copied into the workspace
  (`psalm.xml`, `.phan/config.php`), pinning each server to the same PHP line
  the rest of the suite measures.
- `conformance/src/Lsp/lsp-probe.mjs` — dependency-free Node LSP client. Takes
  a spec JSON (command, workspace, files to open, probes), performs the
  handshake, waits out indexing, runs the probes sequentially, prints one JSON
  blob. Knows nothing about any particular server.
- `conformance/src/Lsp/*.php` — the catalog of launchable servers, anchor
  resolution, workspace preparation, and grading.
- `conformance/src/run-lsp-probes.php` (`make run-lsp-probes`) — runs
  everything and writes one committed TOML per server under
  `conformance/results/lsp/`. Deliberately not part of `main.php`: a new probe
  or fixture changes these files and nothing else.

Servers measured: all eight — Intelephense, Phpactor, Psalm, devsense-php-ls,
PHPantom, php-lsp, Phan, and Laravel LSP. How the last four are obtained
differs from the Composer/npm norm: devsense-php-ls is its own npm namespace
(`make install-devsense-php-ls` — phpy bundles a separately versioned copy of
the same engine, which is deliberately not reused), php-lsp and PHPantom
ship only as per-platform binaries on GitHub releases
(`make install-php-lsp` / `make install-phpantom`; the binaries are
git-ignored, and `update-tools` tracks their releases but cannot track what
is installed), and Laravel LSP is `composer require laravel/lsp` into
`vendor-bin/laravel-lsp/` (`make install-laravel-lsp`). Laravel LSP is
function-specific — it will not initialize without an `artisan` file, and
its hover/completion/definition answers are about env keys, routes,
views and translations, not PHP types. Capability and PHP-type hover
still run on the small fixtures (with a stub `artisan`). Framework
probes run in a second session against the Gate imageboard submodule
(`references/laravel-gate-image-board`, pinned in
`conformance/lsp/laravel/corpus.toml`): the tree is copied without
`vendor/`, then the checkout's `vendor/` is symlinked so `artisan tinker`
works. `make install-laravel-corpus` installs that vendor tree. The
psysh navigation layer is skipped.

## Traps that shaped the design

These cost real debugging time; each one is now load-bearing in the code.

- **macOS temp dirs break Phan.** `sys_get_temp_dir()` returns a path behind
  the `/var -> /private/var` symlink. Phan realpaths files before publishing
  diagnostics, so its URIs said `file:///private/var/...` while every URI the
  client sent said `file:///var/...`; nothing matched and every probe came
  back empty — *deterministically, but only when run through PHP*, since ad
  hoc shells tend to test from already-resolved paths. The workspace path is
  realpath'd before anything sees it (`ProbeRunner::makeTempDir`).
- **Psalm's variable hover depends on which files are open.** In one session,
  hover on a variable statement answers while only its file is open, and
  answers `null` for the same position once a second file has been opened
  (function-symbol hover keeps working either way). The hover conformance
  cases therefore run as their own session that opens `hover-types.php` and
  nothing else — which is also the honest design: what a hover shows should
  not depend on unrelated open files.
- **Probing too early answers everything from a cold index.** Phan needs
  several seconds under the polyfill parser before its first
  `publishDiagnostics`; a fixed warmup raced it and lost. The client waits for
  the diagnostics stream to go quiet (each publish re-arms a settle timer) and
  gives a server that never publishes a longer index timeout before probing
  anyway.
- **Psalm's server needs its config spelled out.** `psalm-language-server`
  without `-c`/`-r` sits silently instead of answering `initialize`; the
  workspace's `psalm.xml` and `-r {workspace}` are not optional niceties.
- **The TOML encoder does not round-trip quotes.** internal/toml 1.1.2 writes
  a string containing single quotes as a literal string, which its own parser
  rejects — and hover text quotes whatever a server said (Phan's
  `` `'alpha'|'beta'` `` found it immediately). `LspResultFile` writes the
  result files with JSON-escaped basic strings instead, the same reason
  `ResultRepository` rolls its own encoding.
- **The two freemium servers gate paid features in opposite ways.** A free
  Intelephense *omits* them from its handshake (`renameProvider: false`), so
  they measure as "not advertised"; DEVSENSE *advertises* rename and then
  refuses the call with `-32803 Feature Rename Refactoring is not licensed`.
  The grader maps licence-refusal errors to their own `gated` verdict so the
  same commercial fact does not render as a dash for one server and a scary
  "Error" for the other. A run on a machine with licence keys would measure
  the licensed shapes.
- **A precise answer is not always the expected spelling.** php-lsp
  constant-folds `strlen('subject')` and hovers the literal `7` — a subtype
  of the expected `int`, not a miss — and PHPantom spells the integer range
  `int<1..100>` where PHPStan-lineage tools write `int<1, 100>`. The hover
  patterns accept both; when adding cases, write `precise` patterns for the
  type, not for one tool's pretty-printer.
- **A correct definition jump is a range, not a line.** Intelephense answers
  go-to-definition with a range that *starts at the declaration's docblock*;
  grading by exact line called every documented symbol "wrong location". The
  grader accepts any returned range in the right file that covers the
  declaration line.
- **Indexers must be waited for, not raced.** Reference sets over a real
  project come from the server's index, and Phpactor's was still building
  when a fixed warmup expired — half the reference set was silently missing.
  The client advertises `window.workDoneProgress` and holds the probes until
  every `$/progress` token has ended (capped by the index timeout), which
  took Phpactor from partial scores to its real ones.
- **Psalm 6.16.1 dies on psysh's test suite.** Scanning
  `test/Command/ListCommand/ConstantEnumeratorTest.php`, its
  `const NAN_CONSTANT = \NAN;` makes `TLiteralFloat` coerce NAN to string —
  a PHP error under 8.5 — and the whole server crashes inside `initialize`.
  The navigation psalm.xml scopes `projectFiles` to `src/` (every probe
  lands there anyway). Upstream this is
  [vimeo/psalm#11831](https://github.com/vimeo/psalm/issues/11831); commit
  `ca151242` fixed it on master (2026-05-16) but no published release —
  6.16.1 or 7.0.0-beta19 — contains it yet. The minimal repro
  (`const NAN_CONSTANT = \NAN;` alone) is posted on that issue; drop the
  src/-only scoping once a release ships the fix.
- **php-lsp goes unresponsive on the corpus.** It advertises
  workspace-wide pull diagnostics and appears to analyse the whole project
  on open; on psysh every navigation probe — definition included — times
  out. This holds across 0.22.0 and 0.24.1: the 0.24 line's startup and
  mention-index work fixed its capability layer (implementation,
  references and workspace-symbol probes now answer) but not navigation
  on a large corpus. The runner gives php-lsp a short navigation window
  (5s per probe, 15s to settle, against the 30s/90s the answering servers
  get), since a server that never replies costs the full window per symbol
  and a verdict is reached just as well in 5s — a 30s window records the
  identical timeouts. Recorded as measured; a per-layer failure only marks
  that layer, so its fixture results still stand.
- **Laravel LSP will not initialize without `artisan`.** The handler returns
  `-32602 Initialize request root URI must be a Laravel project` when that
  file is missing. The capability session still uses a presence-only stub
  so PHP-type probes have a workspace. Framework probes use the Gate
  submodule instead: copy without `vendor/` / `node_modules` / `.git`,
  symlink the checkout's `vendor/`, and overlay `gate.env` as `.env`
  (Gate's own `.env` is gitignored). Helper keys live in
  `app/Support/LspSurface.php`; unknown keys stay off HTTP paths. Pest
  helper generation stays off. Navigation on psysh is skipped. If the
  submodule is missing the stub session still records the helpers.php
  env probes; if `vendor/` is missing, route/view/config/translation
  probes run and come back empty (tinker cannot boot).
- **The code-action probe was asking where nobody had an answer.** It used
  to anchor on the fixture's deliberate type error, which reads as the
  obvious place to ask for a quickfix. No server offers one there. Phpactor
  and php-lsp returned nothing at all, and the actions DEVSENSE and
  PHPantom did return were extract-refactors unrelated to the error, so two
  of the four advertising servers were recorded `empty` for a capability
  they have. Phpactor's author supplied the explanation in
  [#5](https://github.com/zonuexe/php-typing-conformance/issues/5): its code
  actions are coupled to its diagnostics, and core Phpactor publishes none
  for this file. Asking at an error the server has no opinion about
  therefore measures whether it publishes diagnostics, which is already its
  own row. The anchor is now the `private Greeter $greeter;` declaration,
  where the generate-accessor and promote-constructor families live and all
  four servers answer. Worth remembering when adding a capability probe: a
  position that exercises the *capability* beats a position that looks
  thematically right.

## Reading the results

Each `conformance/results/lsp/<tool>.toml` stores the graded rows *and* the
raw handshake JSON (`raw_capabilities`), so any cell can be re-audited without
re-running the server. `version` is what the binary reports; `server_info` is
what the server said about itself over the protocol (often empty — only Psalm
and Phpactor fill it).
