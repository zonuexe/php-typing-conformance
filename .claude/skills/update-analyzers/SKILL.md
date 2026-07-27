---
name: update-analyzers
description: Update the analyzers and language servers this conformance suite tracks, re-measure whatever moved, and rebuild the report. Use whenever the user wants tool versions bumped or checked in php-typing-conformance — "update the analyzers", "check for new releases", "re-run mago on the new version", "アナライザを最新にして", "ツールのバージョン上げて", "依存を更新して", "新しい版で測り直して" — and also when they mention update-tools, releases.toml, vendor-bin, or say the report's version numbers look stale. Reach for it even when the request sounds like a plain `composer update`: in this repository updating a tool changes measurements, so it is never just an install.
metadata:
  internal: true
---

# Updating the tracked analyzers

Updating a tool here is not a dependency bump, it is a re-measurement. The
matrix says what each analyzer answered *at the version in `vendor-bin/`*, so
an install that is not followed by a re-run leaves the report claiming results
the new binary never produced. That is the failure this workflow exists to
prevent, and it is why the steps below end at a rebuilt report rather than at a
successful `composer require`.

## What is in scope

The analyzers and language servers in `conformance/data/releases.toml`. Two
things are deliberately outside it:

- **pzoom** is built from a local Rust checkout and publishes no releases, so
  `update-tools` reports `no feed` for it and leaves it alone. Say so rather
  than trying to update it.
- **The four language servers nothing installs** (phpactor, devsense-php-ls,
  phpantom, php-lsp) are described by the report but never run. `--apply`
  records their new releases; there is nothing to re-measure.

The runner's own dependencies (`conformance/composer.json`) and the
`references/` submodules are separate concerns. Leave them unless asked.

## The loop

### 1. See what moved

```sh
GITHUB_TOKEN=$(gh auth token) make update-tools
```

The token matters: GitHub allows sixty unauthenticated API requests an hour and
one run asks about ten repositories, so without it the GitHub-hosted tools all
come back `feed unavailable (HTTP 403: API rate limit exceeded...)`. If `gh` is
not authenticated, run without the token and expect that to happen; the script
names the reason rather than failing silently.

Four columns, and the differences between them are the point:

| Column | Question it answers |
| --- | --- |
| INSTALLED | what `vendor-bin/` has, so what the matrix measured |
| INSTALLABLE | what Composer would install now, resolved against this platform |
| RECORDED | what `data/releases.toml` says, which the report shows |
| UPSTREAM | what the release feed says today |

`upstream X not installable here` means a release exists that this machine's
PHP cannot take. Report it; do not try to force it.

### 2. Install and record

```sh
GITHUB_TOKEN=$(gh auth token) make update-tools APPLY=1
```

This requires the newest release into each Composer namespace, installs the
newest tag for each npm one, and rewrites `data/releases.toml`. It uses
`require` rather than `update` on purpose — a caret constraint on a 0.x version
stops at the next minor, so `^0.60.0` could never reach 0.62.0 by itself.

It stops before re-running anything, which is the next step's job.

### 3. Re-run the tools that changed

One invocation per updated tool:

```sh
php conformance/src/main.php --tool mago
```

The filter runs and persists only that tool and **skips report regeneration on
purpose**, so the other columns keep the results they were measured with. Only
tools whose INSTALLED version actually moved need this; re-running an unchanged
tool rewrites its `version.toml` with identical content and wastes minutes.

One install is not always one result set. **PHPStan writes two**: `phpstan` and
`phpstan-strict` are the same binary under different configs, so updating it
means running both, or the strict column keeps reporting a version that is no
longer installed.

```sh
php conformance/src/main.php --tool phpstan
php conformance/src/main.php --tool phpstan-strict
```

Each run analyses ~100 files and takes a couple of minutes, because every
analyzer is booted once per test file. Run them in the foreground and let them
finish; a wait-loop that polls for the process is easy to get wrong and buys
nothing.

Expect this step to be where a new release bites — see the section below.

### 4. Rebuild the report

```sh
make render-report-html
```

`conformance/results/updated.toml` looks after itself: the runner rewrites the
stamp only when the data digest moves, so a no-op re-run leaves it alone.

### 5. Read what changed before saying anything

```sh
.claude/skills/update-analyzers/scripts/verdict-diff.sh
```

It prints every changed result file with its old and new verdict, so a run that
only rewrote `version.toml` is visibly different from one that moved five tests
from Fail to Pass. Read it before summarising: "updated mago and mir" is much
less useful to the user than which tests changed answer.

Pass → Fail deserves attention rather than a shrug. It may be a genuine
regression upstream worth an issue, or a changed message the adapter now
mis-parses.

## When a new version breaks the adapter

This is the part worth slowing down for. Each analyzer is wrapped by an adapter
in `conformance/src/Checker/<Tool>Checker.php` that parses its output, and a new
release can change that output without changing a single verdict. The symptom is
an exception during step 3, usually `Failed to parse <Tool> output for ...`.

The way through it is to run the tool by hand exactly as the adapter does.
Read the `analyse()` method, reproduce its command in the shell, and look at
what actually comes back:

```sh
./vendor-bin/mago/vendor/bin/mago --workspace "$PWD/conformance" --colors never \
  analyze --reporting-format json conformance/tests/enums_backed_cases.php
```

A real example from Mago 1.45: it began printing `INFO` lines on the same
stream as its report, both before the payload and after it —
`{"issues": []} INFO No issues found.` — so taking everything from the first
brace to the end of the output stopped being valid JSON. The fix was to cut the
object out from the first brace to the last, which tolerates chatter on either
side.

Two habits that make this quick:

- Check `--help` or the tool's changelog when a flag or subcommand seems to
  have moved; Steins, for instance, gained a `version` subcommand in 0.1.1 and
  the adapter had been pinning a version string because none existed before.
- Fix the adapter, then re-run step 3 for that tool alone before moving on.

Adapter fixes belong to the tool, not to the update: they stay correct whether
or not the rest of the update happens.

## Handing back

Report, in this order: what was already current, what was installed, what broke
and how it was fixed, and which verdicts moved. Be specific about the last one —
name the tests.

A release that fixes something the suite does not test yet is worth a sentence
too. `gh release view <tag> --repo <owner>/<repo>` reads the notes, and a fix
with no test behind it is a gap this project exists to close — mention it, and
leave writing the test to whoever asked.

Leave the commits to the user. If they ask for them, the natural split follows
the order the work happened in, so each commit stands on its own:

1. adapter fixes (they are bug fixes, independent of any version bump)
2. `vendor-bin/*/composer.json` + `composer.lock` + `data/releases.toml`
3. the re-measured `conformance/results/**` and `updated.toml`

The generated HTML is git-ignored and rebuilt by CI on push, so it never
appears in a commit.
