# Contributing

## Clone Setup

This repository contains several heavy Git submodules under `references/`. They are kept as sparse checkouts so contributors do not need to download entire upstream repositories.

After cloning this repository, initialize the submodules with:

```sh
make init-submodules
```

Do not replace this with a full recursive checkout unless you specifically need complete upstream histories and files.

## What `make init-submodules` Does

The `init-submodules` target:

- initializes each `references/*` submodule with blob filtering,
- avoids a full checkout,
- applies sparse checkout to the documentation-relevant paths,
- checks out only the parts needed for local reference work.

Current reference repositories covered by this target:

- `references/fig-standards/`
- `references/mago/`
- `references/phpstan/`
- `references/psalm/`
- `references/phpDocumentor/`
- `references/noverify/`
- `references/phan.wiki/`

## Development Notes

- Use `references/` for lookup and citation, not for project implementation.
- Use tool-local binaries under `vendor-bin/` when working with analyzers.
- Keep architecture decisions in `docs/design-doc.md`.
- Keep developer workflow notes in `AGENTS.md`.
