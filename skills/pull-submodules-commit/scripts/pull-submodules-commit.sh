#!/usr/bin/env bash
set -euo pipefail

commit_message="${1:-Update reference submodules}"

reference_paths=(
  references/python-typing
  references/fig-standards
  references/mago
  references/phpstan
  references/psalm
  references/phpDocumentor
  references/noverify
  references/phan.wiki
)

if ! git rev-parse --show-toplevel >/dev/null 2>&1; then
  echo "Not inside a git repository." >&2
  exit 1
fi

repo_root="$(git rev-parse --show-toplevel)"
cd "$repo_root"

if ! make -n pull-submodules >/dev/null 2>&1; then
  echo "Make target 'pull-submodules' was not found." >&2
  exit 1
fi

make pull-submodules

changed_paths=()

for path in "${reference_paths[@]}"; do
  if [[ -n "$(git status --short -- "$path")" ]]; then
    changed_paths+=("$path")
  fi
done

if [[ "${#changed_paths[@]}" -eq 0 ]]; then
  echo "No reference submodule updates to commit."
  exit 0
fi

git add -- "${changed_paths[@]}"
git commit -m "$commit_message" -- "${changed_paths[@]}"
