#!/usr/bin/env bash
#
# Which verdicts moved in the results, compared with HEAD.
#
# A re-run rewrites a tool's version.toml whether or not anything it measured
# changed, so "files changed" says nothing on its own. This separates the two:
# tests whose Pass/Fail answer moved, and tests where only the diagnostic text
# did.
#
# Usage: verdict-diff.sh [tool ...]      # all changed tools by default

set -uo pipefail

cd "$(git rev-parse --show-toplevel)" || exit 1

if [ "$#" -gt 0 ]; then
    paths=()
    for tool in "$@"; do
        paths+=("conformance/results/${tool}/")
    done
else
    paths=('conformance/results/')
fi

# Against HEAD, not the index: a re-run's output is worth reading whether
# or not some of it has already been staged.
changed=$(git diff HEAD --name-only -- "${paths[@]}" | grep -E '/[^/]+\.toml$' | grep -v -E '/(version|updated)\.toml$')

if [ -z "$changed" ]; then
    echo "No measured results changed."
    exit 0
fi

verdict_of() {
    # conformance_automated = 'Pass' -> Pass
    sed -n "s/^conformance_automated = //p" | tr -d "'\"" | head -1
}

moved=0
text_only=0

printf '%-8s %-52s %s\n' 'TOOL' 'TEST' 'VERDICT'

for file in $changed; do
    tool=$(basename "$(dirname "$file")")
    test=$(basename "$file" .toml)
    before=$(git show "HEAD:${file}" 2>/dev/null | verdict_of)
    after=$(verdict_of < "$file")

    if [ "$before" != "$after" ]; then
        printf '%-8s %-52s %s -> %s\n' "$tool" "$test" "${before:-none}" "${after:-none}"
        moved=$((moved + 1))
    else
        printf '%-8s %-52s %s (diagnostics only)\n' "$tool" "$test" "${after:-none}"
        text_only=$((text_only + 1))
    fi
done

printf '\n%d verdict(s) moved, %d file(s) changed wording only.\n' "$moved" "$text_only"
