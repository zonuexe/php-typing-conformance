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
