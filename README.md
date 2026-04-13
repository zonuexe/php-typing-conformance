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
