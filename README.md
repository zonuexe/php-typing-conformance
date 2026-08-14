# php-typing-conformance

An automated conformance test suite that compares how different PHP static analysis tools interpret native types and PHPDoc annotations. Results are continuously built against the latest tool versions.

This project is inspired by the Python typing project's repository: [python/typing](https://github.com/python/typing). How the conformance material here relates to that upstream suite is summarized in [`conformance/README.md`](conformance/README.md).

## What "conformance" means here

PHP has no agreed typing specification beyond the runtime type declarations.
Everything else — PHPDoc types, generics, conditional types, integer ranges —
exists because one tool implemented it and others adopted it, or because a
convention became de facto without ever being written down as a norm. So where
Python's suite measures conformance to the typing spec, this corpus cannot: it
is instead a collection of typing features, each originating in a specific
tool's implementation or in de-facto convention, with the expected behaviour
stated inline by the test itself. `// T` markers name the type spelling a test
probes, `// E` markers name the diagnostics it should produce, and `// V`
markers name values the spelling admits (so a tool that also rejects those
is not counted as enforcing); those expectations are the conformance target.

What the report then measures is how every tool covering the same ground
responds to a feature it did not originate. The suite keeps two questions
apart: **recognition** — does the tool resolve the spelling at all? — and
**enforcement** — does it then reject the values the spelling excludes? A tool
may implement the feature faithfully (recognized and enforced), degrade
gracefully (recognized but enforced partially or not at all, typically by
falling back to a wider type), or not cover it and report the spelling itself
as an error. That last outcome is recorded as an honest "not implemented", not
as a defect: complaining about a dialect you do not implement is legitimate
behaviour. The marker syntax is described in
[`conformance/tests/README.md`](conformance/tests/README.md).

## Repository Setup

This repository includes several heavy reference submodules under `references/`. Do not fully checkout all submodules by default.

After cloning the repository, initialize the reference submodules with sparse checkout:

```sh
make init-submodules
```

This fetches only the documentation-relevant parts of each submodule, which keeps local setup smaller and faster.

Current references include `python-typing`, `fig-standards`, `mago`, `phpstan`, `psalm`, `phpDocumentor`, `phan.wiki`, and `noverify`.

## Keeping the tools current

```sh
make update-tools            # report what has a newer release upstream
make update-tools APPLY=1    # install those, and record the new releases
```

Each analyzer and language server states where it publishes releases, so this
asks GitHub, npm or Packagist directly and lines up four answers: what
`vendor-bin/` has **installed**, what the package manager would **install**
now, what `conformance/data/releases.toml` **records** as upstream's newest,
and what **upstream** says today. Composer resolves the installable version
against the platform, so a release that wants a PHP this machine does not have
shows up as installable-below-upstream rather than as a phantom update.

`APPLY=1` installs what can be installed and records the current releases;
re-run the suite afterwards to measure the new versions.

GitHub allows 60 unauthenticated API requests an hour, which one run can
exhaust. Pass a token to raise it:

```sh
GITHUB_TOKEN=$(gh auth token) make update-tools
```

## The PHP version everything is measured against

The corpus uses current PHP syntax, so the language version each tool reads is
stated rather than inherited. Both `composer.json` files require `php-64bit`
and pin `config.platform.php` to the **first release of the newest PHP line** —
`8.5.0` today. The `.0` is deliberate: the platform version is a floor, so the
suite resolves against what the whole line guarantees instead of against
whichever patch release happens to be installed on the machine that ran it.

Every analyzer that exposes a target-version knob is set to the same line —
`phpVersion` for PHPStan and Psalm, `--target-php-version` for Phan,
`--php-version` for Mago and mir, `environment.phpVersion` for Intelephense —
or to the highest version it accepts when it cannot reach that far. NoVerify,
phpy and Steins expose nothing to set and read the corpus on their own terms.
[`docs/analyzer-adapters.md`](docs/analyzer-adapters.md) documents that per tool,
along with how each one is invoked, reports its version, and gets its output
normalized.

Left to their defaults the tools disagree, and one targeting an older version
reports parse noise where the test wanted a type verdict. `conformance/psalm.xml`
carries an explicit `phpVersion` for exactly that reason: Psalm otherwise infers
its target from `require.php` in `composer.json` and picks the *lowest* version
the constraint admits. Bumping the pinned line means re-running the suite, since
it can move any tool's verdict.

## The language servers, measured over the protocol

The analyzers are measured through their CLIs; the language servers get their
own measurement over LSP itself:

```sh
make run-lsp-probes
```

That launches every server headless — Intelephense, Phpactor, Psalm,
devsense-php-ls, PHPantom, php-lsp, Phan — records what each `initialize`
handshake advertises,
exercises one probe per advertised capability against a small fixture
workspace, and asks the typing question over the protocol too: hovering a
variable whose type the annotation or the narrowing established, does the
server show that type? When the steins-survey checkout is present, a fourth
layer measures dependency-graph navigation on a real project (psysh): per
symbol kind, does go-to-definition land on the declaration, and how much of
the complete reference set does find-references enumerate from the server's
own index? Results are committed as one TOML per server under
`conformance/results/lsp/` and rendered as their own matrices in the report,
strictly apart from the reference table of what each project claims about
itself. php-lsp and PHPantom ship only as per-platform GitHub release
binaries; `make install-php-lsp` and `make install-phpantom` fetch them.
[`docs/language-servers.md`](docs/language-servers.md) documents the method
and the traps.

## Debug helpers (dump, trace, assert)

Besides Pass/Fail soundness rows, the report has a **Debug features** matrix for
type-inspection surfaces each analyzer ships for humans and fixtures:
`\PHPStan\dumpType()` / `dumpPhpDocType()` / `Testing\assert*`, `@psalm-trace`,
`@phan-debug-var`, `\Mago\inspect()`, mir `@trace` (MIR0221). Function-style
helpers and annotation-style traces answer the same question — “what type is
this here?” — but differ in syntax, message shape, line attribution, and how
foreign tools react (undefined function vs silence).
[`docs/debug-features.md`](docs/debug-features.md) compares them in prose.

## The report

Running the analyzers writes one TOML file per tool and test under
`conformance/results/`, and those files are committed. The HTML report is not:
it derives entirely from them, so it is built instead of stored.

```sh
composer install --working-dir=conformance
make render-report-html
```

That writes `conformance/results/index.html` and a page per test under
`conformance/results/tests/`, both git-ignored. To read it the way it is published, serve it with
PHP's built-in server:

```sh
make serve
```

That renders every page per request from the same data, so it needs no build
first and picks up template edits on reload.

GitHub Actions installs and renders the same way on every push to master and
publishes the result to GitHub Pages, so the published site is always rendered
from the committed data rather than from whatever HTML someone last regenerated
by hand.

## Copyright

```
Copyright 2025 USAMI Kenta <tadsan@zonu.me>

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```
