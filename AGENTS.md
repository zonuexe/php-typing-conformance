# Development Notes

## Repository Purpose

This repository is building a PHP static-analysis conformance suite. Right now, the codebase is mostly:

- Composer metadata,
- isolated analyzer installations,
- design documentation,
- external reference material.

There is not yet a `conformance/` implementation subtree.

## Current Directory Layout

```text
.
├── AGENTS.md
├── README.md
├── LICENSE
├── composer.json
├── composer.lock
├── docs/
│   └── design-doc.md
├── references/
│   ├── fig-standards/
│   ├── mago/
│   ├── noverify/
│   ├── phan.wiki/
│   ├── phpDocumentor/
│   ├── phpstan/
│   └── psalm/
├── vendor/
│   ├── bin/
│   └── bamarni/composer-bin-plugin
└── vendor-bin/
    ├── mago/
    ├── noverify/
    ├── phan/
    ├── phpstan/
    └── psalm/
```

## What Lives Where

- `docs/`: design and architecture documents.
- `references/`: external material used as normative or explanatory references.
- `vendor-bin/`: one isolated Composer environment per analyzer.
- `vendor/`: root-level Composer plugin dependencies and shared Composer bin shims.

## Tool Installation Rules

The normal binary path pattern is:

```text
vendor-bin/<tool>/vendor/bin/<tool>
```

Concrete paths currently present:

- `vendor-bin/phpstan/vendor/bin/phpstan`
- `vendor-bin/psalm/vendor/bin/psalm`
- `vendor-bin/phan/vendor/bin/phan`
- `vendor-bin/mago/vendor/bin/mago`

Related helper binaries also exist for some tools, especially under Psalm and Phan.

## NoVerify Exception

NoVerify is bootstrapped differently.

Bootstrap command:

```text
vendor-bin/noverify/vendor/bin/noverify-get
```

Installed executable after bootstrap:

```text
vendor/bin/noverify
```

Operationally:

- do not assume `vendor-bin/noverify/vendor/bin/noverify` exists,
- run the bootstrap tool first if NoVerify has not yet been installed,
- invoke NoVerify from `vendor/bin/noverify`.

## References

Current external reference checkouts:

- `references/fig-standards/`: `php-fig/fig-standards` Git submodule
- `references/mago/`: Mago source and docs
- `references/phpDocumentor/`: phpDocumentor source and docs
- `references/phpstan/`: PHPStan source and website docs
- `references/psalm/`: Psalm source and docs
- `references/phan.wiki/`: Phan wiki mirror
- `references/noverify/`: NoVerify source and docs

Use this subtree for specification lookup and citation, not for local implementation work.

These submodules are intentionally heavy upstream repositories. Contributors should not fully checkout them by default.

After cloning the repository, initialize them with:

```text
make init-submodules
```

That target uses sparse checkout and only pulls the documentation-relevant parts of each reference repository.

Practical usage:

- use `fig-standards` for PSR and FIG-level reference material,
- use `mago` for Mago rule and analyzer behavior documentation,
- use `phpDocumentor` for PHPDoc semantics and documentation conventions,
- use analyzer-specific directories when documenting tool-specific behavior, support gaps, or extensions.

## Development Conventions

- Prefer tool-local binaries over globally installed commands.
- Treat `vendor-bin/<tool>/composer.lock` as the authoritative version pin for that analyzer.
- Keep future conformance runner code separate from `vendor-bin/`.
- Treat `vendor-bin/` and `references/` as infrastructure and source material, not as places to add project logic.
- Put architecture-level decisions in `docs/design-doc.md`.
- Put implementation and workflow notes for contributors in `AGENTS.md`.

## Expected Next Step

When implementation begins, add a dedicated `conformance/` subtree rather than mixing runtime code into the repository root. That subtree should eventually contain:

- test files,
- fixtures,
- checker adapters,
- result persistence,
- report generation.
