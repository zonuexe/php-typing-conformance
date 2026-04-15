---
name: pull-submodules-commit
description: Use when the user wants Codex to update all `references/*` git submodules in `php-typing-conformance` by running `make pull-submodules` and commit the resulting gitlink changes in the parent repository.
---

# Pull Submodules Commit

Use this skill only in the `php-typing-conformance` repository.

## Workflow

1. Confirm the repository root contains the `pull-submodules` Make target.
2. Run `make pull-submodules` from the repository root.
3. Inspect `git status --short -- references` to see which submodule pointers changed.
4. If no `references/*` entries changed, report that there is nothing to commit and stop.
5. Commit only the changed `references/*` gitlink entries. Do not include unrelated working tree changes.

## Preferred Command

Prefer the helper script:

```bash
./skills/pull-submodules-commit/scripts/pull-submodules-commit.sh
```

You may pass a custom commit message:

```bash
./skills/pull-submodules-commit/scripts/pull-submodules-commit.sh "Update reference submodules"
```

## Guardrails

- Do not edit files under `references/`; the goal is to update submodule pointers in the parent repo.
- Do not stage unrelated files.
- If `make pull-submodules` fails because network or sandbox permissions are blocked, request escalation instead of working around it.
- If the working tree already has user changes outside `references/*`, leave them untouched.
- If the user asks for a different commit message, pass it through to the helper script.

## Manual Fallback

If the script needs to be adjusted, keep the same behavior:

```bash
make pull-submodules
git status --short -- references
git add -- references/python-typing references/fig-standards references/mago references/phpstan references/psalm references/phpDocumentor references/noverify references/phan.wiki
git commit -m "Update reference submodules" -- references/python-typing references/fig-standards references/mago references/phpstan references/psalm references/phpDocumentor references/noverify references/phan.wiki
```
