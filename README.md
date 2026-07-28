# php-typing-conformance

An automated conformance test suite that compares how different PHP static analysis tools interpret native types and PHPDoc annotations. Results are continuously built against the latest tool versions.

This project is inspired by the Python typing project's repository: [python/typing](https://github.com/python/typing). How the conformance material here relates to that upstream suite is summarized in [`conformance/README.md`](conformance/README.md).

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
